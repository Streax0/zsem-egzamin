<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/autoloader.php';
require_once __DIR__ . '/../api/health.php';

use App\Core\DbBackup;

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
$backupService = new DbBackup($pdo ?? null);
$backupDir = $backupService->getBackupDir();

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

    if ($action === 'create_backup') {
        $customPass = trim((string)($_POST['custom_passphrase'] ?? ''));
        try {
            $res = $backupService->createBackup(
                null, 
                null, 
                true, 
                $customPass !== '' ? $customPass : null
            );
            $msg = "Utworzono kopię zapasową bazy: <strong>{$res['filename']}</strong> ({$res['size_formatted']}) oraz zaszyfrowaną AES-256-GCM <strong>{$res['encrypted_filename']}</strong> ({$res['encrypted_size_formatted']}).";
            setSessionMessage('success', $msg);
        } catch (Throwable $e) {
            setSessionMessage('error', 'Błąd podczas tworzenia kopii zapasowej: ' . $e->getMessage());
        }
        redirect('system_health.php');
        exit;
    }

    if ($action === 'decrypt_backup') {
        $sourceType = (string)($_POST['source_type'] ?? 'server');
        $customKey = trim((string)($_POST['custom_key'] ?? ''));
        $outputMode = (string)($_POST['output_mode'] ?? 'download');
        $keyToUse = ($customKey !== '') ? $customKey : null;

        $sourceFilePath = null;
        $tempUpload = false;

        try {
            if ($sourceType === 'upload' && !empty($_FILES['backup_file']['tmp_name'])) {
                if ($_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Błąd przesyłania pliku (kod ' . $_FILES['backup_file']['error'] . ').');
                }
                $sourceFilePath = $_FILES['backup_file']['tmp_name'];
                $tempUpload = true;
            } else {
                $selectedFile = basename((string)($_POST['selected_file'] ?? ''));
                if ($selectedFile === '') {
                    throw new RuntimeException('Nie wybrano pliku do odszyfrowania.');
                }
                $sourceFilePath = $backupDir . '/' . $selectedFile;
            }

            if (!file_exists($sourceFilePath)) {
                throw new RuntimeException('Wskazany plik kopii nie istnieje.');
            }

            $tempSql = sys_get_temp_dir() . '/decrypted_' . uniqid() . '.sql';
            $decryptResult = $backupService->decryptFile($sourceFilePath, $tempSql, $keyToUse);

            if ($outputMode === 'download') {
                if (ob_get_level()) {
                    ob_end_clean();
                }
                $downloadName = 'restored_database_' . date('Y-m-d_His') . '.sql';
                header('Content-Type: application/sql; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $downloadName . '"');
                header('Content-Length: ' . (string)filesize($tempSql));
                readfile($tempSql);
                @unlink($tempSql);
                if ($tempUpload && file_exists($sourceFilePath)) {
                    @unlink($sourceFilePath);
                }
                exit;
            } else {
                $destServerFile = $backupDir . '/restored_' . date('Y-m-d_His') . '.sql';
                if (!@rename($tempSql, $destServerFile)) {
                    @copy($tempSql, $destServerFile);
                    @unlink($tempSql);
                }
                setSessionMessage('success', "Plik został pomyślnie odszyfrowany i zapisany w katalogu kopii: <strong>" . basename($destServerFile) . "</strong> ({$decryptResult['size_formatted']}). Weryfikacja integralności AEAD: OK.");
            }
        } catch (Throwable $e) {
            setSessionMessage('error', 'Błąd odszyfrowania: ' . $e->getMessage());
        } finally {
            if (isset($tempSql) && file_exists($tempSql)) {
                @unlink($tempSql);
            }
            if ($tempUpload && $sourceFilePath && file_exists($sourceFilePath)) {
                @unlink($sourceFilePath);
            }
        }

        redirect('system_health.php');
        exit;
    }

    if ($action === 'download_backup') {
        $file = basename((string)($_POST['filename'] ?? ''));
        $fullPath = $backupDir . '/' . $file;
        if ($file !== '' && file_exists($fullPath)) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . (string)filesize($fullPath));
            readfile($fullPath);
            exit;
        }
        setSessionMessage('error', 'Plik nie został odnaleziony.');
        redirect('system_health.php');
        exit;
    }

    if ($action === 'delete_backup') {
        $file = basename((string)($_POST['filename'] ?? ''));
        $fullPath = $backupDir . '/' . $file;
        if ($file !== '' && file_exists($fullPath) && (str_ends_with($file, '.sql.gz') || str_ends_with($file, '.sql.gz.enc') || str_ends_with($file, '.sql'))) {
            @unlink($fullPath);
            setSessionMessage('success', "Plik kopii <strong>{$file}</strong> został pomyślnie usunięty.");
        } else {
            setSessionMessage('error', 'Nieprawidłowy plik do usunięcia.');
        }
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
            usleep(5000);
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

// Scan available backups
$backupFiles = [];
if (is_dir($backupDir)) {
    $scanned = glob($backupDir . '/*.*');
    if (is_array($scanned)) {
        foreach ($scanned as $f) {
            $fn = basename($f);
            if ($fn === '.htaccess' || $fn === 'index.html') continue;
            $isEnc = str_ends_with($f, '.enc');
            $isGz = str_ends_with($f, '.sql.gz');
            $isPlain = str_ends_with($f, '.sql');
            $backupFiles[] = [
                'path'      => $f,
                'filename'  => $fn,
                'size'      => (int)@filesize($f),
                'size_fmt'  => round((@filesize($f) ?: 0) / 1024, 2) . ' KB',
                'mtime'     => (int)@filemtime($f),
                'date'      => date('d.m.Y H:i:s', (int)@filemtime($f)),
                'is_enc'    => $isEnc,
                'is_gz'     => $isGz,
                'is_plain'  => $isPlain,
                'algo'      => $isEnc ? 'AES-256-GCM (AEAD)' : ($isGz ? 'GZIP (Skompresowana)' : 'Czysty SQL'),
            ];
        }
        usort($backupFiles, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    }
}

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
    .pulse-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
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
        background: rgba(0, 0, 0, 0.06);
        padding: 0.25rem 0.5rem;
        border-radius: 0.35rem;
    }
    [data-bs-theme="dark"] .code-snippet,
    .dark .code-snippet {
        background: rgba(255, 255, 255, 0.08);
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
                        <p class="text-body-secondary mb-0">Monitorowanie parametrów środowiskowych, bazy danych, pamięci podręcznej i wolnych zapytań.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#decryptBackupModal">
                            <i class="bi bi-unlock-fill me-1"></i> Odszyfruj Kopię Bazy
                        </button>
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
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Health Status Header -->
                <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="pulse-indicator bg-success"></span>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge <?php echo $statusBadgeClass; ?> fs-6 px-3 py-2 rounded-pill"><?php echo $statusLabel; ?></span>
                                <span class="text-body-secondary small">Ostatnie sprawdzenie: <span class="fw-semibold text-body"><?php echo date('H:i:s d.m.Y'); ?></span></span>
                            </div>
                        </div>
                        <div class="text-body-secondary small">
                            Czas wykonania diagnostyki: <strong class="text-body"><?php echo $healthReport['execution_time_ms']; ?> ms</strong>
                        </div>
                    </div>
                </div>

                <!-- 4 Metric Cards -->
                <div class="row g-4 mb-4">
                    <!-- DB Status Card -->
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm rounded-4 p-4 bg-body h-100">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="text-body-secondary small text-uppercase fw-semibold">Baza Danych</span>
                                <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-database fs-5"></i>
                                </div>
                            </div>
                            <div class="fs-3 fw-bold mb-1 text-body">
                                <?php echo $dbCheck['latency_ms'] ?? 0; ?> <span class="fs-6 text-body-secondary">ms</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center text-body-secondary small mt-2">
                                <span>Status: <strong class="text-success"><?php echo strtoupper((string)($dbCheck['status'] ?? 'UP')); ?></strong></span>
                                <span>Silnik: <code class="px-2 py-0.5 rounded bg-body-secondary"><?php echo htmlspecialchars((string)($dbCheck['driver'] ?? 'mysql'), ENT_QUOTES, 'UTF-8'); ?></code></span>
                            </div>
                        </div>
                    </div>

                    <!-- RAM Card -->
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm rounded-4 p-4 bg-body h-100">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="text-body-secondary small text-uppercase fw-semibold">Zużycie RAM</span>
                                <div class="p-2 rounded-3 bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-cpu fs-5"></i>
                                </div>
                            </div>
                            <div class="fs-3 fw-bold mb-1 text-body">
                                <?php echo $memCheck['current_mb'] ?? 0; ?> <span class="fs-6 text-body-secondary">MB</span>
                            </div>
                            <div class="progress mt-2 mb-2" style="height: 6px;">
                                <div class="progress-bar bg-info" style="width: <?php echo min(100, max(5, $memCheck['used_percent'] ?? 10)); ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between text-body-secondary small">
                                <span>Peak: <strong><?php echo $memCheck['peak_mb'] ?? 0; ?> MB</strong></span>
                                <span>Limit: <strong><?php echo htmlspecialchars((string)($memCheck['limit'] ?? '128M'), ENT_QUOTES, 'UTF-8'); ?></strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- Cache Card -->
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm rounded-4 p-4 bg-body h-100">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="text-body-secondary small text-uppercase fw-semibold">Cache & OPcache</span>
                                <div class="p-2 rounded-3 bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-lightning-charge fs-5"></i>
                                </div>
                            </div>
                            <div class="fs-3 fw-bold mb-1 text-body">
                                <?php 
                                    $opHitRate = $cacheCheck['opcache']['hit_rate'] ?? null;
                                    echo $opHitRate !== null ? "{$opHitRate}%" : 'Aktywny';
                                ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center text-body-secondary small mt-2">
                                <span>APCu: <strong class="<?php echo !empty($cacheCheck['app_cache']['apcu_enabled']) ? 'text-success' : 'text-body-secondary'; ?>"><?php echo !empty($cacheCheck['app_cache']['apcu_enabled']) ? 'Włączone' : 'Wyłączone'; ?></strong></span>
                                <span>Pliki: <strong><?php echo $cacheCheck['app_cache']['items_count'] ?? 0; ?></strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- Storage Card -->
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm rounded-4 p-4 bg-body h-100">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="text-body-secondary small text-uppercase fw-semibold">Uprawnienia Katalogów</span>
                                <div class="p-2 rounded-3 bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-folder-check fs-5"></i>
                                </div>
                            </div>
                            <div class="fs-3 fw-bold mb-1 text-body">
                                <?php echo !empty($storageCheck['all_writable']) ? 'Wszystkie OK' : 'Ograniczone'; ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center text-body-secondary small mt-2">
                                <span>Wolne miejsce:</span>
                                <strong><?php echo $storageCheck['free_space_mb'] !== null ? round($storageCheck['free_space_mb'] / 1024, 1) . ' GB' : 'Nieznane'; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════════════ -->
                <!-- DATABASE BACKUPS & DECRYPTION PANEL (AES-256-GCM) -->
                <!-- ═══════════════════════════════════════════════════════════════ -->
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-body mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                        <div>
                            <h2 class="h5 fw-bold mb-1 d-flex align-items-center gap-2">
                                <i class="bi bi-shield-lock-fill text-primary"></i> Kopie Zapasowe & Odszyfrowywanie Bazy (AES-256-GCM)
                            </h2>
                            <p class="text-body-secondary small mb-0">Automatyczne i ręczne tworzenie bezpiecznych, wojskowo zaszyfrowanych kopii bazy z danymi użytkowników.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#createBackupModal">
                                <i class="bi bi-cloud-arrow-up-fill me-1"></i> Utwórz Nową Kopię Bazy
                            </button>
                            <button type="button" class="btn btn-success btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#decryptBackupModal">
                                <i class="bi bi-key-fill me-1"></i> Odszyfruj Plik
                            </button>
                        </div>
                    </div>

                    <!-- Security Notice Banner -->
                    <div class="p-3 bg-body-tertiary rounded-3 border border-secondary border-opacity-10 mb-4 d-flex align-items-center gap-3">
                        <div class="p-2 rounded-3 bg-success bg-opacity-10 text-success flex-shrink-0">
                            <i class="bi bi-shield-check fs-4"></i>
                        </div>
                        <div class="small">
                            <strong class="text-body d-block mb-0.5">Bezpieczeństwo Kluczy Szyfrujących (Zero-Knowledge Architecture)</strong>
                            <span class="text-body-secondary">Klucz szyfrowania nie jest zapisywany w plikach na dysku obok kopii. Jest bezpiecznie odczytywany ze zmiennej środowiskowej serwera (<code>BACKUP_ENCRYPTION_KEY</code> w <code>.env</code>) lub z hasła podanego ręcznie przez administratora podczas tworzenia/odszyfrowywania.</span>
                        </div>
                    </div>

                    <!-- Backups Archive Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-body-secondary small">
                                    <th>Nazwa Pliku Kopii</th>
                                    <th>Data Utworzenia</th>
                                    <th>Rozmiar</th>
                                    <th>Format & Szyfrowanie</th>
                                    <th class="text-end">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($backupFiles)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-body-secondary py-4">
                                            <i class="bi bi-archive fs-4 d-block mb-2 text-body-tertiary"></i>
                                            Brak zapisanych kopii w katalogu <code>data/backups/</code>. Kliknij „Utwórz Nową Kopię Bazy”, aby wygenerować pierwszą kopię.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($backupFiles as $bf): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi <?php echo $bf['is_enc'] ? 'bi-file-earmark-lock2-fill text-success fs-5' : ($bf['is_gz'] ? 'bi-file-earmark-zip-fill text-primary fs-5' : 'bi-filetype-sql text-info fs-5'); ?>"></i>
                                                    <span class="font-monospace fw-semibold"><?php echo htmlspecialchars($bf['filename'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            </td>
                                            <td class="small text-body-secondary"><?php echo $bf['date']; ?></td>
                                            <td class="small font-monospace"><?php echo $bf['size_fmt']; ?></td>
                                            <td>
                                                <?php if ($bf['is_enc']): ?>
                                                    <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">
                                                        <i class="bi bi-lock-fill me-1"></i>AES-256-GCM
                                                    </span>
                                                <?php elseif ($bf['is_gz']): ?>
                                                    <span class="badge bg-primary bg-opacity-15 text-primary border border-primary border-opacity-25 px-2 py-1 rounded-pill">
                                                        <i class="bi bi-file-zip me-1"></i>GZIP
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-15 text-secondary border border-secondary border-opacity-25 px-2 py-1 rounded-pill">
                                                        <i class="bi bi-file-text me-1"></i>Czysty SQL
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1">
                                                    <?php if ($bf['is_enc']): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-2" title="Odszyfruj ten plik" onclick="openDecryptForFile('<?php echo htmlspecialchars($bf['filename'], ENT_QUOTES, 'UTF-8'); ?>')">
                                                            <i class="bi bi-unlock-fill me-1"></i>Odszyfruj
                                                        </button>
                                                    <?php endif; ?>
                                                    <form method="POST" action="system_health.php" class="d-inline m-0">
                                                        <?php echo csrfTokenField('admin_health'); ?>
                                                        <input type="hidden" name="action" value="download_backup">
                                                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($bf['filename'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Pobierz archiwum">
                                                            <i class="bi bi-download"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="system_health.php" class="d-inline m-0" data-admin-confirm="Czy na pewno chcesz usunąć tę kopię zapasową?">
                                                        <?php echo csrfTokenField('admin_health'); ?>
                                                        <input type="hidden" name="action" value="delete_backup">
                                                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($bf['filename'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Usuń plik">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <!-- Latency Benchmark Card -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-4 p-4 bg-body h-100">
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
                            <p class="text-body-secondary small mb-3">Wykonuje sekwencję synchronicznych zapytań kontrolnych do silnika PDO i mierzy odchylenie czasu odpowiedzi.</p>

                            <?php if ($benchmarkResult): ?>
                                <div class="p-3 bg-body-tertiary rounded-3 border border-secondary border-opacity-10 mb-3">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="text-body-secondary small">Min</div>
                                            <div class="h5 fw-bold text-success mb-0"><?php echo $benchmarkResult['min']; ?> ms</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-body-secondary small">Średnia</div>
                                            <div class="h5 fw-bold text-primary mb-0"><?php echo $benchmarkResult['avg']; ?> ms</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-body-secondary small">Max</div>
                                            <div class="h5 fw-bold text-warning mb-0"><?php echo $benchmarkResult['max']; ?> ms</div>
                                        </div>
                                    </div>
                                    <hr class="border-secondary border-opacity-10 my-2">
                                    <div class="text-body-secondary small text-center">
                                        Pomiary: <code><?php echo implode(' ms, ', $benchmarkResult['latencies']); ?> ms</code>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="p-4 text-center text-body-secondary bg-body-tertiary rounded-3 border border-secondary border-opacity-10">
                                    <i class="bi bi-cpu fs-3 text-body-tertiary d-block mb-2"></i>
                                    Kliknij „Uruchom Test Jitter”, aby zmierzyć rzeczywisty czas odpowiedzi bazy danych.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Directory Permissions Table -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-4 p-4 bg-body h-100">
                            <h2 class="h5 fw-bold mb-3"><i class="bi bi-hdd-network me-2 text-success"></i>Katalogi Danych (Storage Permissions)</h2>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead>
                                        <tr class="text-body-secondary small">
                                            <th>Katalog</th>
                                            <th>Istnieje</th>
                                            <th>Zapis</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($storageCheck['directories'] ?? []) as $name => $info): ?>
                                            <?php 
                                                $displayPath = ($name === 'data') ? 'data/' : 'data/' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '/';
                                            ?>
                                            <tr>
                                                <td><code class="px-2 py-0.5 rounded bg-body-secondary"><?php echo $displayPath; ?></code></td>
                                                <td>
                                                    <?php if (!empty($info['exists'])): ?>
                                                        <span class="badge bg-success bg-opacity-15 text-success">TAK</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger bg-opacity-15 text-danger">BRAK</span>
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
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-body mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold mb-0"><i class="bi bi-hourglass-bottom me-2 text-warning"></i>Rejestr Wolnych Zapytań SQL (&ge; 100 ms)</h2>
                        <span class="text-body-secondary small">Plik: <code>data/logs/slow_queries.log</code></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-body-secondary small">
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
                                        <td colspan="5" class="text-center text-body-secondary py-4">
                                            <i class="bi bi-check-circle text-success me-1 fs-5 align-middle"></i> Brak zarejestrowanych wolnych zapytań. Wszystkie zapytania wykonują się w czasie poniżej 100ms.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentSlowQueries as $sq): ?>
                                        <tr>
                                            <td class="small text-body-secondary"><?php echo htmlspecialchars((string)($sq['timestamp'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo htmlspecialchars((string)($sq['duration_ms'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?> ms</span>
                                            </td>
                                            <td class="small"><code><?php echo htmlspecialchars((string)($sq['caller'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                            <td>
                                                <code class="code-snippet text-truncate d-inline-block" style="max-width: 380px;" title="<?php echo htmlspecialchars((string)($sq['sql'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars((string)($sq['sql'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                </code>
                                            </td>
                                            <td class="small text-body-secondary">
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
            </div>
        </main>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <script>
            function openDecryptForFile(filename) {
                const select = document.getElementById('modalSelectedFile');
                if (select) {
                    select.value = filename;
                }
                const modalEl = document.getElementById('decryptBackupModal');
                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            }
        </script>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>

<!-- Modal: Create Backup with Optional Custom Passphrase -->
<div class="modal fade" id="createBackupModal" tabindex="-1" aria-labelledby="createBackupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 bg-body">
            <div class="modal-header border-bottom border-secondary border-opacity-10">
                <h5 class="modal-title fw-bold" id="createBackupModalLabel">
                    <i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>Tworzenie Kopii Zapasowej Bazy
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <form method="POST" action="system_health.php">
                <?php echo csrfTokenField('admin_health'); ?>
                <input type="hidden" name="action" value="create_backup">
                <div class="modal-body p-4">
                    <p class="text-body-secondary small mb-3">
                        Kopia zapasowa zostanie wygenerowana jako skompresowany plik <code>.sql.gz</code> oraz zaszyfrowana w standardzie <strong>AES-256-GCM (AEAD)</strong>.
                    </p>
                    <div class="mb-3">
                        <label for="createPassInput" class="form-label fw-bold small text-uppercase text-body-secondary">Własne hasło szyfrowania (opcjonalne):</label>
                        <input type="password" class="form-control" name="custom_passphrase" id="createPassInput" placeholder="Pozostaw puste, aby użyć klucza z .env">
                        <div class="form-text">Jeśli pozostawisz to pole puste, zostanie użyty bezpieczny klucz środowiskowy serwera (<code>BACKUP_ENCRYPTION_KEY</code>).</div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-10 p-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i> Utwórz Kopię Teraz
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Decrypt Backup Tool -->
<div class="modal fade" id="decryptBackupModal" tabindex="-1" aria-labelledby="decryptBackupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 bg-body">
            <div class="modal-header border-bottom border-secondary border-opacity-10">
                <h5 class="modal-title fw-bold" id="decryptBackupModalLabel">
                    <i class="bi bi-key-fill text-success me-2"></i>Odszyfrowywanie Kopii Bazy Danych (AES-256-GCM)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <form method="POST" action="system_health.php" enctype="multipart/form-data">
                <?php echo csrfTokenField('admin_health'); ?>
                <input type="hidden" name="action" value="decrypt_backup">
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 d-flex align-items-center gap-2 mb-4 rounded-3">
                        <i class="bi bi-info-circle-fill fs-5"></i>
                        <div class="small">
                            Pliki zaszyfrowane w standardzie AES-256-GCM zawierają integralny tag uwierzytelniający AEAD. Po weryfikacji plik zostanie wyodrębniony do gotowego formatu <code>.sql</code>.
                        </div>
                    </div>

                    <!-- Source Type Tabs -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-body-secondary">Źródło pliku kopii:</label>
                        <div class="d-flex gap-3 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="source_type" id="srcServer" value="server" checked onclick="document.getElementById('serverFileGroup').classList.remove('d-none'); document.getElementById('uploadFileGroup').classList.add('d-none');">
                                <label class="form-check-label fw-semibold" for="srcServer">
                                    Wybierz kopię zapisaną na serwerze
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="source_type" id="srcUpload" value="upload" onclick="document.getElementById('serverFileGroup').classList.add('d-none'); document.getElementById('uploadFileGroup').classList.remove('d-none');">
                                <label class="form-check-label fw-semibold" for="srcUpload">
                                    Prześlij plik <code>.enc</code> z dysku
                                </label>
                            </div>
                        </div>

                        <!-- Server File Select -->
                        <div id="serverFileGroup" class="mb-3">
                            <label for="modalSelectedFile" class="form-label small text-body-secondary">Plik kopii z serwera:</label>
                            <select class="form-select font-monospace" name="selected_file" id="modalSelectedFile">
                                <?php 
                                    $encBackups = array_filter($backupFiles, fn($b) => $b['is_enc']);
                                    if (empty($encBackups)):
                                ?>
                                    <option value="">Brak zaszyfrowanych kopii na serwerze</option>
                                <?php else: ?>
                                    <?php foreach ($encBackups as $eb): ?>
                                        <option value="<?php echo htmlspecialchars($eb['filename'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($eb['filename'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo $eb['size_fmt']; ?> | <?php echo $eb['date']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Upload File Input -->
                        <div id="uploadFileGroup" class="mb-3 d-none">
                            <label for="modalUploadFile" class="form-label small text-body-secondary">Wybierz zaszyfrowany plik (.sql.gz.enc):</label>
                            <input class="form-control" type="file" name="backup_file" id="modalUploadFile" accept=".enc,.gz">
                        </div>
                    </div>

                    <!-- Custom Key -->
                    <div class="mb-3">
                        <label for="customKeyInput" class="form-label fw-bold small text-uppercase text-body-secondary">Hasło / Klucz szyfrowania:</label>
                        <input type="password" class="form-control font-monospace" name="custom_key" id="customKeyInput" placeholder="Pozostaw puste, aby użyć klucza ze zmiennej środowiskowej .env">
                        <div class="form-text">Jeśli plik został zabezpieczony własnym hasłem, wpisz je powyżej. W przeciwnym razie system użyje klucza środowiskowego.</div>
                    </div>

                    <!-- Output Mode -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase text-body-secondary">Co zrobić z odszyfrowaną bazą:</label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 h-100 bg-body-tertiary">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="output_mode" id="outDownload" value="download" checked>
                                        <label class="form-check-label fw-bold" for="outDownload">
                                            <i class="bi bi-download text-primary me-1"></i> Pobierz plik .SQL
                                        </label>
                                        <div class="small text-body-secondary mt-1">Odszyfrowuje i natychmiast pobiera czysty plik .SQL do przeglądarki.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 h-100 bg-body-tertiary">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="output_mode" id="outServer" value="server">
                                        <label class="form-check-label fw-bold" for="outServer">
                                            <i class="bi bi-hdd-fill text-success me-1"></i> Zapisz na serwerze
                                        </label>
                                        <div class="small text-body-secondary mt-1">Zapisuje odszyfrowany plik .SQL w katalogu <code>data/backups/</code>.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-10 p-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold">
                        <i class="bi bi-unlock-fill me-1"></i> Odszyfruj Kopię Bazy
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Clear Cache Confirmation -->
<div class="modal fade" id="clearCacheModal" tabindex="-1" aria-labelledby="clearCacheModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 bg-body">
            <div class="modal-header border-bottom border-secondary border-opacity-10">
                <h5 class="modal-title fw-bold" id="clearCacheModalLabel"><i class="bi bi-trash3 text-danger me-2"></i>Czyszczenie Pamięci Podręcznej</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <form method="POST" action="system_health.php">
                <?php echo csrfTokenField('admin_health'); ?>
                <input type="hidden" name="action" value="clear_cache">
                <div class="modal-body p-4">
                    <p class="text-body-secondary small">Wybierz zakres czyszczenia pamięci podręcznej platformy:</p>
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
                <div class="modal-footer border-top border-secondary border-opacity-10">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-3">Wyczyść pamięć</button>
                </div>
            </form>
        </div>
    </div>
</div>
