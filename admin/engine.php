<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();

if (!isLoggedIn()) {
    http_response_code(403);
    setSessionMessage('error', 'Musisz być zalogowany.');
    redirect('../login.php');
    exit;
}

$userRole = (string)($_SESSION['role'] ?? 'user');
if (!roleHasAdminAccess($userRole)) {
    http_response_code(403);
    setSessionMessage('error', 'Brak uprawnień do panelu silnika i security.');
    redirect('../index.php');
    exit;
}

$engine = \App\Core\Engine::getInstance();
if (!$engine->isBooted()) {
    $engine->boot();
}

$configStore = $engine->getConfig();
$cacheManager = $engine->getCache();
$firewall = $engine->getFirewall() ?? new \App\Security\Firewall();
$waf = $engine->getWaf() ?? new \App\Security\Waf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!validateCsrfToken($token, 'admin_engine') && !validateCsrfToken($token, 'admin') && !validateCsrfToken($token, '')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('engine.php');
        exit;
    }

    $action = (string)($_POST['action'] ?? '');

    switch ($action) {
        case 'update_config':
            $configStore->set('maintenance_mode', !empty($_POST['maintenance_mode']));
            $configStore->set('maintenance_message', trim((string)($_POST['maintenance_message'] ?? '')));
            $configStore->set('maintenance_until', trim((string)($_POST['maintenance_until'] ?? '')));
            $configStore->set('minification_enabled', !empty($_POST['minification_enabled']));
            $configStore->set('compression_enabled', !empty($_POST['compression_enabled']));

            $rawWaf = strtolower(trim((string)($_POST['waf_level'] ?? 'medium')));
            $wafLevel = in_array($rawWaf, ['disabled', 'low', 'medium', 'strict'], true) ? $rawWaf : 'medium';
            $configStore->set('waf_level', $wafLevel);

            $configStore->set('csrf_enforced', !empty($_POST['csrf_enforced']));

            setSessionMessage('success', 'Ustawienia silnika zostały pomyślnie zaktualizowane.');
            redirect('engine.php');
            exit;

        case 'clear_cache':
            $cacheType = strtolower(trim((string)($_POST['cache_type'] ?? 'all')));
            if (!in_array($cacheType, ['apcu', 'file', 'assets', 'all'], true)) {
                $cacheType = 'all';
            }
            $success = $cacheManager->clear($cacheType);
            if ($success) {
                setSessionMessage('success', 'Pamięć podręczna (' . htmlspecialchars($cacheType, ENT_QUOTES, 'UTF-8') . ') została wyczyszczona.');
            } else {
                setSessionMessage('error', 'Błąd podczas czyszczenia pamięci podręcznej (' . htmlspecialchars($cacheType, ENT_QUOTES, 'UTF-8') . ').');
            }
            redirect('engine.php');
            exit;

        case 'ban_ip':
            $ip = trim((string)($_POST['ip'] ?? ''));
            $reason = trim((string)($_POST['reason'] ?? 'Manual ban'));
            if ($reason === '') {
                $reason = 'Manual ban';
            }
            $duration = (int)($_POST['duration'] ?? 86400);
            if ($duration < 0) {
                $duration = 86400;
            }

            if ($ip !== '') {
                if ($firewall->banIp($ip, $reason, $duration)) {
                    setSessionMessage('success', 'Adres IP ' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . ' został zablokowany.');
                } else {
                    setSessionMessage('error', 'Nie udało się zablokować adresu IP.');
                }
            } else {
                setSessionMessage('error', 'Podaj poprawny adres IP.');
            }
            redirect('engine.php');
            exit;

        case 'unban_ip':
            $ip = trim((string)($_POST['ip'] ?? ''));
            if ($ip !== '') {
                if ($firewall->unbanIp($ip)) {
                    setSessionMessage('success', 'Adres IP ' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . ' został odblokowany.');
                } else {
                    setSessionMessage('error', 'Nie udało się odblokować adresu IP.');
                }
            } else {
                setSessionMessage('error', 'Podaj poprawny adres IP do odblokowania.');
            }
            redirect('engine.php');
            exit;

        case 'clear_waf_logs':
            $waf->clearLogs();
            setSessionMessage('success', 'Logi WAF zostały pomyślnie wyczyszczone.');
            redirect('engine.php');
            exit;

        case 'bump_assets':
            $newVer = $configStore->bumpAssetVersion();
            setSessionMessage('success', 'Wersja assetów (CSS/JS) została zaktualizowana do: ' . htmlspecialchars($newVer, ENT_QUOTES, 'UTF-8'));
            redirect('engine.php');
            exit;

        case 'export_logs':
            $logs = $waf->getLogs();
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="security_waf_logs_' . date('Y-m-d_H-i-s') . '.json"');
            echo json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
    }
}

$currentConfig = $configStore->all();
$maintenanceMode = (bool)($currentConfig['maintenance_mode'] ?? false);
$maintenanceMessage = (string)($currentConfig['maintenance_message'] ?? 'Trwają planowane prace serwisowe. Zapraszamy wkrótce.');
$maintenanceUntil = (string)($currentConfig['maintenance_until'] ?? '');
$minificationEnabled = (bool)($currentConfig['minification_enabled'] ?? true);
$compressionEnabled = (bool)($currentConfig['compression_enabled'] ?? true);
$wafLevel = (string)($currentConfig['waf_level'] ?? 'medium');
$csrfEnforced = (bool)($currentConfig['csrf_enforced'] ?? true);

$cacheStats = $cacheManager->getStats();
$cacheHits = (int)($cacheStats['hits'] ?? 0);
$cacheMisses = (int)($cacheStats['misses'] ?? 0);
$totalCacheReqs = $cacheHits + $cacheMisses;
$hitRatio = $totalCacheReqs > 0 ? round(($cacheHits / $totalCacheReqs) * 100, 1) : 0.0;

$bootTimeMs = round((microtime(true) - ($engine->getBootTime() ?: microtime(true))) * 1000, 2);
$memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

$wafStats = $waf->getStats();
$wafLogs = $waf->getLogs();
$totalBlockedAttacks = (int)($wafStats['blocked'] ?? count($wafLogs));
$bannedIps = $firewall->getBannedIps();

$flash = getSessionMessage();

$pageTitle = 'Silnik i Security — Panel Admina';
$extraCss = ['assets/css/dashboard-new.css'];
$base_url = '../';
include '../includes/header.php';
?>
<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include '../includes/topbar.php'; ?>
        <main class="content-body" id="main-content">
            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
                            <i class="bi bi-cpu text-primary"></i> Silnik i Security
                        </h1>
                        <p class="text-muted mb-0">Zarządzaj jądrem aplikacji, pamięcią podręczną, regułami WAF oraz zablokowanymi adresami IP.</p>
                    </div>
                </div>

                <?php if (!empty($flash['message'])): ?>
                    <div class="alert alert-<?php echo ($flash['type'] ?? '') === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                        <?php echo htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- 1. Performance Metrics Panel -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card h-100 border-0 shadow-sm rounded-4 p-3 bg-body-tertiary">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 fs-3">
                                    <i class="bi bi-speedometer2"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Czas rozruchu (Boot)</div>
                                    <div class="fs-4 fw-bold"><?php echo $bootTimeMs; ?> ms</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 border-0 shadow-sm rounded-4 p-3 bg-body-tertiary">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-3 bg-info bg-opacity-10 text-info rounded-3 fs-3">
                                    <i class="bi bi-memory"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Szczytowy pobór RAM</div>
                                    <div class="fs-4 fw-bold"><?php echo $memoryPeak; ?> MB</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 border-0 shadow-sm rounded-4 p-3 bg-body-tertiary">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 fs-3">
                                    <i class="bi bi-pie-chart"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Trafienia Cache (Hit/Miss)</div>
                                    <div class="fs-4 fw-bold"><?php echo $hitRatio; ?>% <span class="fs-6 fw-normal text-muted">(<?php echo $cacheHits; ?>/<?php echo $cacheMisses; ?>)</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 border-0 shadow-sm rounded-4 p-3 bg-body-tertiary">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3 fs-3">
                                    <i class="bi bi-shield-x"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Zablokowane ataki WAF</div>
                                    <div class="fs-4 fw-bold"><?php echo $totalBlockedAttacks; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <!-- 2. Dynamic Engine Settings Panel -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-4 h-100 p-4 bg-body">
                            <h2 class="h5 fw-bold mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-sliders text-primary"></i> Konfiguracja Silnika
                            </h2>
                            <form method="POST" action="engine.php">
                                <?php echo csrfTokenField('admin_engine'); ?>
                                <input type="hidden" name="action" value="update_config">

                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="maintenance_mode" name="maintenance_mode" value="1" <?php echo $maintenanceMode ? 'checked' : ''; ?> onchange="document.getElementById('maintenance_details').style.display = this.checked ? 'block' : 'none';">
                                    <label class="form-check-label fw-semibold" for="maintenance_mode">Tryb konserwacji (Maintenance Mode)</label>
                                    <div class="form-text">Blokuje dostęp nie-administratorom podczas prac technicznych.</div>
                                </div>

                                <div class="mb-3 ps-3 border-start border-warning border-3 ms-1" id="maintenance_details" style="<?php echo $maintenanceMode ? '' : 'display: none;'; ?>">
                                    <div class="mb-2">
                                        <label for="maintenance_message" class="form-label small fw-semibold">Komunikat dla użytkowników</label>
                                        <textarea class="form-control form-control-sm" id="maintenance_message" name="maintenance_message" rows="2" placeholder="Trwają planowane prace serwisowe..."><?php echo htmlspecialchars($maintenanceMessage, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                    <div>
                                        <label for="maintenance_until" class="form-label small fw-semibold">Data i godzina zakończenia prac (opcjonalnie)</label>
                                        <input type="datetime-local" class="form-control form-control-sm" id="maintenance_until" name="maintenance_until" value="<?php echo htmlspecialchars($maintenanceUntil, ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="form-text small">Włącza zegar odliczający i automatyczne odświeżenie na stronie konserwacji.</div>
                                    </div>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="minification_enabled" name="minification_enabled" value="1" <?php echo $minificationEnabled ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="minification_enabled">Minifikacja kodu HTML / JS</label>
                                    <div class="form-text">Kompresuje kod odpowiedzi wyjściowej w buforze.</div>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="compression_enabled" name="compression_enabled" value="1" <?php echo $compressionEnabled ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="compression_enabled">Kompresja Gzip / Brotli</label>
                                    <div class="form-text">Włącza nagłówki i kompresję wyjściową odpowiedzi.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="waf_level" class="form-label fw-semibold">Poziom ochrony WAF</label>
                                    <select class="form-select" id="waf_level" name="waf_level">
                                        <option value="disabled" <?php echo $wafLevel === 'disabled' ? 'selected' : ''; ?>>Wyłączony (Disabled)</option>
                                        <option value="low" <?php echo $wafLevel === 'low' ? 'selected' : ''; ?>>Niski (Low)</option>
                                        <option value="medium" <?php echo $wafLevel === 'medium' ? 'selected' : ''; ?>>Średni (Medium - zalecany)</option>
                                        <option value="strict" <?php echo $wafLevel === 'strict' ? 'selected' : ''; ?>>Restrykcyjny (Strict)</option>
                                    </select>
                                    <div class="form-text">Określa czułość sygnatur wykrywania ataków SQLi, XSS i RCE.</div>
                                </div>

                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" role="switch" id="csrf_enforced" name="csrf_enforced" value="1" <?php echo $csrfEnforced ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="csrf_enforced">Wymuszaj ochronę CSRF w WAF</label>
                                    <div class="form-text">Automatyczna weryfikacja tokenów dla żądań POST/PUT/DELETE.</div>
                                </div>

                                <button type="submit" class="btn btn-primary rounded-3 px-4">
                                    <i class="bi bi-save me-1"></i> Zapisz ustawienia
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- 3. Cache Management Panel -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-4 h-100 p-4 bg-body">
                            <h2 class="h5 fw-bold mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-database-dash text-primary"></i> Zarządzanie Pamięcią Podręczną (Cache)
                            </h2>
                            <p class="text-muted small">Aktualny magazyn: <strong><?php echo htmlspecialchars((string)($cacheStats['backend'] ?? 'file'), ENT_QUOTES, 'UTF-8'); ?></strong> | Liczba wpisów: <strong><?php echo (int)($cacheStats['items_count'] ?? 0); ?></strong></p>
                            
                            <div class="row g-2 mt-2">
                                <div class="col-6">
                                    <form method="POST" action="engine.php">
                                        <?php echo csrfTokenField('admin_engine'); ?>
                                        <input type="hidden" name="action" value="clear_cache">
                                        <input type="hidden" name="cache_type" value="all">
                                        <button type="submit" class="btn btn-outline-danger w-100 py-3 rounded-3 d-flex flex-column align-items-center gap-1">
                                            <i class="bi bi-trash3 fs-4"></i>
                                            <span>Wyczyść WSZYSTKO</span>
                                        </button>
                                    </form>
                                </div>
                                <div class="col-6">
                                    <form method="POST" action="engine.php">
                                        <?php echo csrfTokenField('admin_engine'); ?>
                                        <input type="hidden" name="action" value="clear_cache">
                                        <input type="hidden" name="cache_type" value="apcu">
                                        <button type="submit" class="btn btn-outline-warning w-100 py-3 rounded-3 d-flex flex-column align-items-center gap-1">
                                            <i class="bi bi-lightning-charge fs-4"></i>
                                            <span>Wyczyść APCu</span>
                                        </button>
                                    </form>
                                </div>
                                <div class="col-6">
                                    <form method="POST" action="engine.php">
                                        <?php echo csrfTokenField('admin_engine'); ?>
                                        <input type="hidden" name="action" value="clear_cache">
                                        <input type="hidden" name="cache_type" value="file">
                                        <button type="submit" class="btn btn-outline-secondary w-100 py-3 rounded-3 d-flex flex-column align-items-center gap-1">
                                            <i class="bi bi-file-earmark-code fs-4"></i>
                                            <span>Wyczyść Pliki Cache</span>
                                        </button>
                                    </form>
                                </div>
                                <div class="col-6">
                                    <form method="POST" action="engine.php">
                                        <?php echo csrfTokenField('admin_engine'); ?>
                                        <input type="hidden" name="action" value="bump_assets">
                                        <button type="submit" class="btn btn-outline-info w-100 py-3 rounded-3 d-flex flex-column align-items-center gap-1">
                                            <i class="bi bi-arrow-repeat fs-4"></i>
                                            <span>Odśwież Wersję Assetów (v<?php echo htmlspecialchars((string)($currentConfig['asset_version'] ?? '1.0.0'), ENT_QUOTES, 'UTF-8'); ?>)</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Security Audit & Ban List Panel -->
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold m-0 d-flex align-items-center gap-2">
                            <i class="bi bi-shield-slash text-danger"></i> Ban List & Security Audit
                        </h2>
                        <form method="POST" action="engine.php" class="m-0">
                            <?php echo csrfTokenField('admin_engine'); ?>
                            <input type="hidden" name="action" value="export_logs">
                            <button type="submit" class="btn btn-sm btn-outline-secondary rounded-3">
                                <i class="bi bi-download me-1"></i> Pobierz Logi (JSON)
                            </button>
                        </form>
                    </div>

                    <!-- Manual Ban Form -->
                    <div class="bg-body-tertiary p-3 rounded-3 mb-4">
                        <h3 class="h6 fw-bold mb-3">Zablokuj adres IP (Manual Ban)</h3>
                        <form method="POST" action="engine.php" class="row g-2 align-items-center">
                            <?php echo csrfTokenField('admin_engine'); ?>
                            <input type="hidden" name="action" value="ban_ip">
                            
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="ip" placeholder="np. 192.168.1.100" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="reason" placeholder="Powód zablokowania">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="duration">
                                    <option value="3600">1 godzina</option>
                                    <option value="86400" selected>24 godziny</option>
                                    <option value="604800">7 dni</option>
                                    <option value="2592000">30 dni</option>
                                    <option value="0">Bezterminowo</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="bi bi-slash-circle me-1"></i> Zablokuj IP
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Banned IPs Table -->
                    <h3 class="h6 fw-bold mb-2">Zablokowane Adresy IP</h3>
                    <div class="table-responsive mb-4">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Adres IP</th>
                                    <th>Powód</th>
                                    <th>Data zablokowania</th>
                                    <th>Wygasa</th>
                                    <th class="text-end">Akcja</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($bannedIps)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">Brak zablokowanych adresów IP.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($bannedIps as $entry): ?>
                                        <tr>
                                            <td class="fw-semibold"><code><?php echo htmlspecialchars((string)($entry['ip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                            <td><?php echo htmlspecialchars((string)($entry['reason'] ?? 'Brak'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo !empty($entry['banned_at']) ? date('d.m.Y H:i:s', (int)$entry['banned_at']) : '-'; ?></td>
                                            <td><?php echo !empty($entry['expires_at']) ? date('d.m.Y H:i:s', (int)$entry['expires_at']) : 'Bezterminowo'; ?></td>
                                            <td class="text-end">
                                                <form method="POST" action="engine.php" class="d-inline">
                                                    <?php echo csrfTokenField('admin_engine'); ?>
                                                    <input type="hidden" name="action" value="unban_ip">
                                                    <input type="hidden" name="ip" value="<?php echo htmlspecialchars((string)($entry['ip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-check-circle me-1"></i> Odblokuj
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- WAF Audit Logs Viewer -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 fw-bold mb-0">Logi Zdarzeń WAF (Security Audit)</h3>
                        <?php if (!empty($wafLogs)): ?>
                            <form method="POST" action="engine.php" class="d-inline">
                                <?php echo csrfTokenField('admin_engine'); ?>
                                <input type="hidden" name="action" value="clear_waf_logs">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-trash me-1"></i> Wyczyść logi WAF
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Czas</th>
                                    <th>IP</th>
                                    <th>Metoda / URI</th>
                                    <th>Typ Ataku</th>
                                    <th>Parametr</th>
                                    <th>Payload</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($wafLogs)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">Brak zarejestrowanych incydentów WAF.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach (array_reverse($wafLogs) as $log): ?>
                                        <tr>
                                            <td class="small"><?php echo !empty($log['timestamp']) ? date('d.m.Y H:i:s', (int)$log['timestamp']) : '-'; ?></td>
                                            <td><code><?php echo htmlspecialchars((string)($log['ip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                            <td class="small">
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars((string)($log['method'] ?? 'GET'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <code><?php echo htmlspecialchars((string)($log['uri'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo htmlspecialchars((string)($log['attack_type'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td><code><?php echo htmlspecialchars((string)($log['param'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                            <td class="small text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars((string)($log['payload'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                <code><?php echo htmlspecialchars((string)($log['payload'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>
