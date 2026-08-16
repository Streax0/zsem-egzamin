<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/autoloader.php';
require_once __DIR__ . '/../api/health.php';

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
    setSessionMessage('error', 'Brak uprawnień do panelu diagnostyki systemu.');
    redirect('../index.php');
    exit;
}

$engine = class_exists('\\App\\Core\\Engine') ? \App\Core\Engine::getInstance() : null;
if ($engine && !$engine->isBooted()) {
    $engine->boot();
}

$cacheManager = ($engine && $engine->isBooted()) ? $engine->getCache() : new \App\Core\CacheManager();

$benchmarkResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!validateCsrfToken($token, 'admin_health') && !validateCsrfToken($token, 'admin') && !validateCsrfToken($token, '')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('system_health.php');
        exit;
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'clear_cache') {
        $cacheType = (string)($_POST['cache_type'] ?? 'all');
        $cacheManager->clear($cacheType);
        setSessionMessage('success', 'Pamięć podręczna systemu (' . htmlspecialchars($cacheType, ENT_QUOTES, 'UTF-8') . ') została pomyślnie wyczyszczona.');
        redirect('system_health.php');
        exit;
    }

    if ($action === 'benchmark_db') {
        $iterations = 5;
        $latencies = [];
        for ($i = 0; $i < $iterations; $i++) {
            $t0 = microtime(true);
            $stmt = $pdo->query('SELECT 1');
            $stmt->fetchColumn();
            $latencies[] = round((microtime(true) - $t0) * 1000, 3);
            usleep(5000); // 5ms pause
        }
        $min = min($latencies);
        $max = max($latencies);
        $avg = round(array_sum($latencies) / count($latencies), 3);
        $benchmarkResult = [
            'iterations' => $iterations,
            'latencies'  => $latencies,
            'min'        => $min,
            'max'        => $max,
            'avg'        => $avg,
        ];
    }
}

$healthReport = getSystemHealthReport($pdo, true);
$dbCheck = $healthReport['checks']['database'] ?? [];
$memCheck = $healthReport['checks']['memory'] ?? [];
$cacheCheck = $healthReport['checks']['cache'] ?? [];
$storageCheck = $healthReport['checks']['storage'] ?? [];

// Fetch recent slow queries from log file
$slowQueriesLogFile = dirname(__DIR__) . '/data/logs/slow_queries.log';
$recentSlowQueries = [];
if (file_exists($slowQueriesLogFile)) {
    $lines = @file($slowQueriesLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        $lastLines = array_slice($lines, -15);
        foreach ($lastLines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $recentSlowQueries[] = $decoded;
            }
        }
    }
}
$recentSlowQueries = array_reverse($recentSlowQueries);

$statusBadgeClass = match ($healthReport['status']) {
    'healthy' => 'bg-success',
    'degraded' => 'bg-warning text-dark',
    default => 'bg-danger',
};

$statusLabel = match ($healthReport['status']) {
    'healthy' => 'System Sprawny (Healthy)',
    'degraded' => 'Stan Ograniczony (Degraded)',
    default => 'Błąd Krytyczny (Unhealthy)',
};

$flash = getSessionMessage();
$pageTitle = 'Stan Systemu i Diagnostyka — Panel Admina';
$extraCss = ['assets/css/dashboard-new.css'];
$base_url = '../';
include '../includes/header.php';
?>
<style>
    .health-gauge-card {
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 1rem;
        padding: 1.5rem;
        transition: transform 0.2s ease, border-color 0.2s ease;
    }
    .health-gauge-card:hover {
        transform: translateY(-2px);
        border-color: rgba(99, 102, 241, 0.3);
    }
    .pulse-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 6px;
        animation: pulse-ring 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
    }
    @keyframes pulse-ring {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
    .code-snippet {
        font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 0.85rem;
        background: rgba(0, 0, 0, 0.35);
        padding: 0.25rem 0.5rem;
        border-radius: 0.35rem;
    }
</style>

<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include '../includes/topbar.php'; ?>

        <main class="content-body" id="main-content">
            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
                            <i class="bi bi-heart-pulse text-danger"></i> Stan Systemu i Diagnostyka
                        </h1>
                        <p class="text-muted mb-0">Monitorowanie parametrów środowiskowych, bazy danych, pamięci podręcznej i wolnych zapytań.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#clearCacheModal">
                            <i class="bi bi-trash3 me-1"></i> Wyczyść Cache
                        </button>
                        <a href="system_health.php" class="btn btn-primary btn-sm rounded-pill px-3">
                            <i class="bi bi-arrow-clockwise me-1"></i> Odśwież
                        </a>
                    </div>
                </div>

                <?php if (!empty($flash['message'])): ?>
                    <div class="alert alert-<?php echo ($flash['type'] ?? '') === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                        <?php echo htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Health Status Header -->
                <div class="alert health-gauge-card mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="pulse-indicator bg-success"></span>
                        <div>
                            <span class="badge <?php echo $statusBadgeClass; ?> fs-6 px-3 py-2"><?php echo $statusLabel; ?></span>
                            <span class="text-muted small ms-2">Ostatnie sprawdzenie: <?php echo date('H:i:s d.m.Y'); ?></span>
                        </div>
                    </div>
                    <div class="text-muted small">
                        Czas wykonania diagnostyki: <strong class="text-light"><?php echo $healthReport['execution_time_ms']; ?> ms</strong>
                    </div>
                </div>

                <!-- 4 Metric Cards -->
                <div class="row g-4 mb-4">
                    <!-- DB Status Card -->
                    <div class="col-md-6 col-xl-3">
                        <div class="health-gauge-card h-100">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="text-muted small text-uppercase fw-semibold">Baza Danych</span>
                                <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-database fs-5"></i>
                                </div>
                            </div>
                            <div class="h4 fw-bold mb-1">
                                <?php echo $dbCheck['latency_ms'] ?? 0; ?> <span class="fs-6 text-muted">ms</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center text-muted small mt-2">
                                <span>Status: <strong class="text-success"><?php echo strtoupper((string)($dbCheck['status'] ?? 'UP')); ?></strong></span>
                                <span>Silnik: <code><?php echo htmlspecialchars((string)($dbCheck['driver'] ?? 'mysql'), ENT_QUOTES, 'UTF-8'); ?></code></span>
                            </div>
                        </div>
                    </div>

                    <!-- RAM Card -->
                    <div class="col-md-6 col-xl-3">
                        <div class="health-gauge-card h-100">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="text-muted small text-uppercase fw-semibold">Zużycie RAM</span>
                                <div class="p-2 rounded-3 bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-cpu fs-5"></i>
                                </div>
                            </div>
                            <div class="h4 fw-bold mb-1">
                                <?php echo $memCheck['current_mb'] ?? 0; ?> <span class="fs-6 text-muted">MB</span>
                            </div>
                            <div class="progress mt-2 mb-2" style="height: 6px;">
                                <div class="progress-bar bg-info" style="width: <?php echo min(100, max(5, $memCheck['used_percent'] ?? 10)); ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between text-muted small">
                                <span>Peak: <?php echo $memCheck['peak_mb'] ?? 0; ?> MB</span>
                                <span>Limit: <?php echo htmlspecialchars((string)($memCheck['limit'] ?? '128M'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Cache Card -->
                    <div class="col-md-6 col-xl-3">
                        <div class="health-gauge-card h-100">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="text-muted small text-uppercase fw-semibold">Cache & OPcache</span>
                                <div class="p-2 rounded-3 bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-lightning-charge fs-5"></i>
                                </div>
                            </div>
                            <div class="h4 fw-bold mb-1">
                                <?php 
                                    $opHitRate = $cacheCheck['opcache']['hit_rate'] ?? null;
                                    echo $opHitRate !== null ? "{$opHitRate}%" : 'Aktywny';
                                ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center text-muted small mt-2">
                                <span>APCu: <strong class="<?php echo !empty($cacheCheck['app_cache']['apcu_enabled']) ? 'text-success' : 'text-muted'; ?>"><?php echo !empty($cacheCheck['app_cache']['apcu_enabled']) ? 'Włączone' : 'Wyłączone'; ?></strong></span>
                                <span>Pliki: <?php echo $cacheCheck['app_cache']['items_count'] ?? 0; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Storage Card -->
                    <div class="col-md-6 col-xl-3">
                        <div class="health-gauge-card h-100">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="text-muted small text-uppercase fw-semibold">Uprawnienia Katalogów</span>
                                <div class="p-2 rounded-3 bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-folder-check fs-5"></i>
                                </div>
                            </div>
                            <div class="h4 fw-bold mb-1">
                                <?php echo !empty($storageCheck['all_writable']) ? 'Wszystkie OK' : 'Ograniczone'; ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center text-muted small mt-2">
                                <span>Wolne miejsce:</span>
                                <strong><?php echo $storageCheck['free_space_mb'] !== null ? round($storageCheck['free_space_mb'] / 1024, 1) . ' GB' : 'Nieznane'; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <!-- Latency Benchmark Card -->
                    <div class="col-lg-6">
                        <div class="health-gauge-card h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h5 fw-bold mb-0"><i class="bi bi-speedometer2 me-2 text-primary"></i>Benchmark Latencji SQL</h2>
                                <form method="POST" action="system_health.php">
                                    <?php echo csrfTokenField('admin_health'); ?>
                                    <input type="hidden" name="action" value="benchmark_db">
                                    <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill">
                                        <i class="bi bi-play-fill me-1"></i> Uruchom Test Jitter (5x)
                                    </button>
                                </form>
                            </div>
                            <p class="text-muted small">Wykonuje sekwencję synchronicznych zapytań kontrolnych do silnika PDO i mierzy odchylenie czasu odpowiedzi.</p>

                            <?php if ($benchmarkResult): ?>
                                <div class="p-3 bg-dark bg-opacity-50 rounded-3 border border-secondary border-opacity-25 mb-3">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="text-muted small">Min</div>
                                            <div class="h5 fw-bold text-success mb-0"><?php echo $benchmarkResult['min']; ?> ms</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted small">Średnia</div>
                                            <div class="h5 fw-bold text-primary mb-0"><?php echo $benchmarkResult['avg']; ?> ms</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted small">Max</div>
                                            <div class="h5 fw-bold text-warning mb-0"><?php echo $benchmarkResult['max']; ?> ms</div>
                                        </div>
                                    </div>
                                    <hr class="border-secondary border-opacity-25 my-2">
                                    <div class="text-muted small text-center">
                                        Pomiary: <code><?php echo implode(' ms, ', $benchmarkResult['latencies']); ?> ms</code>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="p-3 text-center text-muted bg-dark bg-opacity-25 rounded-3 border border-secondary border-opacity-10">
                                    Kliknij „Uruchom Test Jitter”, aby zmierzyć rzeczywisty czas odpowiedzi bazy.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Directory Permissions Table -->
                    <div class="col-lg-6">
                        <div class="health-gauge-card h-100">
                            <h2 class="h5 fw-bold mb-3"><i class="bi bi-hdd-network me-2 text-success"></i>Katalogi Danych (Storage Permissions)</h2>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead>
                                        <tr class="text-muted small">
                                            <th>Katalog</th>
                                            <th>Istnieje</th>
                                            <th>Zapis</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($storageCheck['directories'] ?? []) as $name => $info): ?>
                                            <tr>
                                                <td><code>data/<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></code></td>
                                                <td>
                                                    <?php if (!empty($info['exists'])): ?>
                                                        <span class="badge bg-success bg-opacity-25 text-success">TAK</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger bg-opacity-25 text-danger">BRAK</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($info['writable'])): ?>
                                                        <span class="badge bg-success"><i class="bi bi-check2"></i> Zapisywalny</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger"><i class="bi bi-x"></i> Tylko do odczytu</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slow Queries Log Viewer -->
                <div class="health-gauge-card mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold mb-0"><i class="bi bi-hourglass-bottom me-2 text-warning"></i>Rejestr Wolnych Zapytań SQL (&ge; 100 ms)</h2>
                        <span class="text-muted small">Plik: <code>data/logs/slow_queries.log</code></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-muted small">
                                    <th>Czas</th>
                                    <th>Czas Trwania</th>
                                    <th>Wywołane Przez</th>
                                    <th>Zapytanie SQL</th>
                                    <th>URI / IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentSlowQueries)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">
                                            <i class="bi bi-check-circle text-success me-1"></i> Brak zarejestrowanych wolnych zapytań. Wszystkie zapytania wykonują się w czasie poniżej 100ms.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentSlowQueries as $sq): ?>
                                        <tr>
                                            <td class="small text-muted"><?php echo htmlspecialchars((string)($sq['timestamp'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo htmlspecialchars((string)($sq['duration_ms'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?> ms</span>
                                            </td>
                                            <td class="small"><code><?php echo htmlspecialchars((string)($sq['caller'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                            <td>
                                                <code class="code-snippet text-truncate d-inline-block" style="max-width: 380px;" title="<?php echo htmlspecialchars((string)($sq['sql'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars((string)($sq['sql'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                </code>
                                            </td>
                                            <td class="small text-muted">
                                                <div><?php echo htmlspecialchars((string)($sq['request_uri'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                <small><?php echo htmlspecialchars((string)($sq['ip'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
        </main>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>

<!-- Modal: Clear Cache Confirmation -->
<div class="modal fade" id="clearCacheModal" tabindex="-1" aria-labelledby="clearCacheModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="clearCacheModalLabel"><i class="bi bi-trash3 text-danger me-2"></i>Czyszczenie Pamięci Podręcznej</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <form method="POST" action="system_health.php">
                <?php echo csrfTokenField('admin_health'); ?>
                <input type="hidden" name="action" value="clear_cache">
                <div class="modal-body">
                    <p class="text-muted small">Wybierz zakres czyszczenia pamięci podręcznej platformy:</p>
                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="cache_type" id="cacheTypeAll" value="all" checked>
                            <label class="form-check-label" for="cacheTypeAll">
                                <strong>Wszystko</strong> (APCu + Pliki cache + Tagi + Assety)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="cache_type" id="cacheTypeTags" value="tags">
                            <label class="form-check-label" for="cacheTypeTags">
                                <strong>Tagi</strong> (Tylko wersje tagów)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="cache_type" id="cacheTypeFiles" value="file">
                            <label class="form-check-label" for="cacheTypeFiles">
                                <strong>Pliki JSON</strong> (Tylko pliki w <code>data/cache/</code>)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-danger rounded-pill">Wyczyść pamięć</button>
                </div>
            </form>
        </div>
    </div>
</div>
