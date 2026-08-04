<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
if (!isLoggedIn() && !isGuestMode()) {
    startGuestSession();
}
requireLogin(true);

$isGuest = isGuestMode();
$userId = $isGuest ? null : (int)$_SESSION['user_id'];
$errors = [];

// Load saved participant data
$savedData = $_SESSION['exam_participant_data'] ?? null;
if (isset($_GET['code']) && !isset($_POST['access_code'])) {
    $_POST['access_code'] = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string)$_GET['code'])));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Nieprawidłowy token CSRF.';
    }

    $code = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string)($_POST['access_code'] ?? ''))));
    $firstName = substr(trim($_POST['first_name'] ?? ''), 0, 100);
    $lastName = substr(trim($_POST['last_name'] ?? ''), 0, 100);
    $classInput = trim($_POST['class'] ?? '');
    $classParsed = parseClassLabel($classInput);
    $class = $classParsed['label'] ?? '';
    $saveData = !$isGuest && isset($_POST['save_data']);
    $privacyConsent = $_POST['privacy_consent'] ?? '';

    // Validate
    if (empty($code)) $errors[] = 'Wprowadź kod sprawdzianu.';
    if (empty($firstName)) $errors[] = 'Podaj imię.';
    if (empty($lastName)) $errors[] = 'Podaj nazwisko.';
    if (!$classParsed) $errors[] = 'Wybierz klasę 1-5, oznaczenie może mieć maksymalnie 2 znaki.';
    if ($privacyConsent !== 'on') $errors[] = 'Zgoda RODO jest wymagana do dołączenia do sprawdzianu.';

    if (empty($errors)) {
        // Find active session with this code
        $stmt = $pdo->prepare("
            SELECT es.id, es.exam_id, es.access_code, es.status, es.started_at, es.paused_at, es.paused_seconds, es.finished_at, es.expires_at, es.created_at, e.title, e.max_participants, e.teacher_id, e.allow_rejoin, e.lock_after_finish,
                   e.max_attempts, e.available_from, e.available_until, u.username as teacher_name
            FROM exam_sessions es
            JOIN exams e ON es.exam_id = e.id
            JOIN users u ON e.teacher_id = u.id
            WHERE es.access_code = ? AND es.status IN ('lobby') AND es.expires_at > NOW()
        ");
        $stmt->execute([$code]);
        $session = $stmt->fetch();

        if (!$session) {
            $errors[] = 'Nieprawidłowy kod, sesja wygasła lub egzamin już się rozpoczął.';
        } else {
            $nowTs = time();
            if (!empty($session['available_from']) && $nowTs < strtotime($session['available_from'])) {
                $errors[] = 'Ten sprawdzian nie jest jeszcze dostępny.';
            }
            if (!empty($session['available_until']) && $nowTs > strtotime($session['available_until'])) {
                $errors[] = 'Okno dostępności tego sprawdzianu już minęło.';
            }

            if (!empty($errors)) {
                // keep validation errors visible below
            } else {
                // Check attempts and active participation
                if ($isGuest) {
                    $guestParticipantId = guestExamParticipantId((int)$session['id']);
                    $stmt = $pdo->prepare("SELECT id, status FROM exam_participants WHERE session_id = ? AND id = ? AND user_id IS NULL AND status != 'removed' ORDER BY id DESC");
                    $stmt->execute([$session['id'], $guestParticipantId]);
                } else {
                    $stmt = $pdo->prepare("SELECT id, status FROM exam_participants WHERE session_id = ? AND user_id = ? AND status != 'removed' ORDER BY id DESC");
                    $stmt->execute([$session['id'], $userId]);
                }
                $participantAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $activeParticipant = null;
                $finishedAttempts = 0;
                foreach ($participantAttempts as $attempt) {
                    if ($attempt['status'] === 'finished') {
                        $finishedAttempts++;
                    } elseif (!$activeParticipant) {
                        $activeParticipant = $attempt;
                    }
                }

                if ($activeParticipant) {
                    // Already joined, go to lobby
                    redirect('lobby.php?session=' . $session['id']);
                } elseif ($finishedAttempts > 0 && (empty($session['allow_rejoin']) || !empty($session['lock_after_finish']))) {
                    $errors[] = 'Ten sprawdzian został już przez Ciebie zakończony.';
                } elseif ($finishedAttempts >= max(1, (int)($session['max_attempts'] ?? 1))) {
                    $errors[] = 'Wykorzystano maksymalną liczbę podejść do tego sprawdzianu.';
                } else {
                    // Check participant limit only for a new attempt
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_participants WHERE session_id = ? AND status != 'removed'");
                    $stmt->execute([$session['id']]);
                    $currentCount = (int)$stmt->fetchColumn();

                    if ($currentCount >= $session['max_participants']) {
                        $errors[] = 'Osiągnięto limit uczestników (' . $session['max_participants'] . ').';
                    } else {

                // Join the session
                $stmt = $pdo->prepare("
                    INSERT INTO exam_participants (session_id, user_id, first_name, last_name, class, status)
                    VALUES (?, ?, ?, ?, ?, 'in_lobby')
                ");
                $stmt->execute([$session['id'], $userId, $firstName, $lastName, $class]);
                if ($isGuest) {
                    rememberGuestExamParticipant((int)$session['id'], (int)$pdo->lastInsertId());
                }

                // Save data if requested
                if ($saveData) {
                    $_SESSION['exam_participant_data'] = [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'class' => $class,
                    ];
                    // Also save to user profile
                    try {
                        $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, class = ?, class_year = ?, class_suffix = ? WHERE id = ?")
                            ->execute([$firstName, $lastName, $class, $classParsed['year'], $classParsed['suffix'], $userId]);
                    } catch (PDOException $e) {
                        // Non-critical
                    }
                }

                redirect('lobby.php?session=' . $session['id']);
                    }
                }
            }
        }
    }
}

// Try to pre-fill from saved data or user profile
if (!$isGuest && !$savedData) {
    try {
        $stmt = $pdo->prepare("SELECT first_name, last_name, class FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch();
        if ($userData && $userData['first_name']) {
            $savedData = $userData;
        }
    } catch (PDOException $e) {}
}

// Get current UI preferences from cookies for server-side theme rendering
$currentTheme = $_COOKIE['user_theme'] ?? 'light';
$currentFontSize = $_COOKIE['user_font_size'] ?? '16';
$currentDensity = $_COOKIE['user_density'] ?? 'comfortable';
$currentAccent = $_COOKIE['user_accent'] ?? '#3b82f6';
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $currentAccent)) {
    $currentAccent = '#3b82f6';
}
$reduceMotion = ($_COOKIE['reduce_motion'] ?? '0') === '1';
$dashboardView = $_COOKIE['dashboard_view'] ?? 'balanced';
$welcomeBannerStyle = $_COOKIE['welcome_banner_style'] ?? 'gradient';

$bodyClasses = [];
$bodyClasses[] = ($currentTheme === 'dark') ? 'dark-mode' : 'light-mode';
if ($currentDensity === 'compact') {
    $bodyClasses[] = 'ui-compact';
}
if ($reduceMotion) {
    $bodyClasses[] = 'reduce-motion';
}
$bodyClasses[] = 'dashboard-view-' . (in_array($dashboardView, ['balanced', 'learning', 'compact']) ? $dashboardView : 'balanced');
$bodyClasses[] = 'welcome-style-' . (in_array($welcomeBannerStyle, ['gradient', 'pure', 'aurora', 'glass']) ? $welcomeBannerStyle : 'gradient');
$bodyClassStr = implode(' ', $bodyClasses);
?>
<!DOCTYPE html>
<html lang="pl" style="color-scheme: <?php echo $currentTheme === 'dark' ? 'dark' : 'light'; ?>; font-size: <?php echo htmlspecialchars($currentFontSize); ?>px; --primary-color: <?php echo htmlspecialchars($currentAccent); ?>; --kolor-glowy: <?php echo htmlspecialchars($currentAccent); ?>;">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dołącz do sprawdzianu – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('../assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('../assets/css/dashboard-new.css')); ?>">
    <script src="<?php echo htmlspecialchars(assetUrl('../assets/js/theme-handler.js')); ?>"></script>
    <style>
        .qr-scan-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .qr-scan-panel {
            display: none;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #d1d5db;
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .qr-scan-panel.active {
            display: block;
        }
        .qr-scan-video {
            width: 100%;
            border-radius: 1rem;
            background: #000;
            height: 210px;
            object-fit: cover;
        }
        .qr-scan-status {
            font-size: 0.95rem;
            color: #475569;
        }
        .qr-scan-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.75rem;
            flex-wrap: wrap;
        }
        .qr-scan-actions .btn {
            flex: 1;
        }
        .qr-desktop-note {
            color: #64748b;
            font-size: .85rem;
            display: none;
        }
        .join-hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            width: fit-content;
            padding: .45rem .8rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, .16);
            color: #fff;
            font-size: .82rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            border: 1px solid rgba(255, 255, 255, .28);
        }
        @media (min-width: 768px) {
            .qr-desktop-note {
                display: block;
            }
        }
        @media (max-width: 767px) {
            .qr-scan-panel {
                background: rgba(248, 250, 252, 0.98);
            }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars($bodyClassStr); ?>">

    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">
                    <div class="join-exam-shell">
                        <div class="join-hero-panel animate-in">
                            <div class="join-hero-icon"><i class="bi bi-qr-code-scan"></i></div>
                            <span class="join-hero-kicker mb-3"><i class="bi bi-lightning-charge"></i> Sprawdzian nauczyciela</span>
                            <h1 class="h2" style="color: #fff;">Dołącz bez odświeżania i czekaj w lobby.</h1>
                            <p>Wpisz kod z tablicy albo zeskanuj QR. Po dołączeniu zobaczysz status sesji i start sprawdzianu w czasie rzeczywistym.</p>
                            <div class="join-steps">
                                <div><span>1</span> Kod lub QR</div>
                                <div><span>2</span> Dane uczestnika</div>
                                <div><span>3</span> Lobby i start bez odświeżania</div>
                            </div>
                        </div>
                        <div class="join-form-panel animate-in">
                            <div class="dashboard-panel" data-join-code-card>
                                <div class="text-center mb-4">
                                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;">
                                        <i class="bi bi-qr-code-scan display-5 text-primary"></i>
                                    </div>
                                    <h2 class="h3 fw-bold">Dołącz do sprawdzianu</h2>
                                    <p class="text-muted"><?php echo $isGuest ? 'Tryb gościa: dane służą tylko do udziału w tej sesji sprawdzianu.' : 'Wprowadź kod otrzymany od nauczyciela'; ?></p>
                                </div>

                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger border-0">
                                        <?php foreach ($errors as $err): ?>
                                            <div><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($err) ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST">
                                    <?php echo csrfTokenField(); ?>
                                    <div class="mb-4">
                                        <div class="d-flex flex-column flex-sm-row align-items-stretch gap-3">
                                            <div class="flex-grow-1">
                                                <label class="form-label fw-semibold" for="access_code">Kod sprawdzianu</label>
                                                <input type="text" name="access_code" id="access_code" class="form-control form-control-lg text-center fw-bold" 
                                                       placeholder="np. A7K9P2" maxlength="20" required autofocus
                                                       style="letter-spacing:0.3rem; font-size:1.5rem;"
                                                       value="<?= htmlspecialchars($_POST['access_code'] ?? '') ?>">
                                            </div>
                                            <div class="d-flex align-items-end qr-mobile-only">
                                                <button type="button" id="scanQrButton" class="btn btn-outline-primary btn-lg w-100">
                                                    <i class="bi bi-qr-code-scan me-2"></i> Skanuj QR
                                                </button>
                                            </div>
                                        </div>
                                        <div class="qr-desktop-note mt-2"><i class="bi bi-camera-video me-1"></i>Skanowanie QR działa na telefonie i komputerze z kamerą. Kod wpisze się automatycznie.</div>
                                        <div id="qrScanPanel" class="qr-scan-panel mt-3">
                                            <video id="qrVideo" class="qr-scan-video" autoplay muted playsinline></video>
                                            <div class="qr-scan-status mt-3" id="qrScanStatus">Naciśnij „Skanuj QR”, aby rozpocząć.</div>
                                            <div class="qr-scan-actions">
                                                <button type="button" id="stopScanButton" class="btn btn-outline-danger">Zatrzymaj</button>
                                                <button type="button" id="fillCodeButton" class="btn btn-primary">Wypełnij kod</button>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-4">
                                    <h3 class="fs-6 fw-bold mb-3"><i class="bi bi-person me-1"></i>Dane uczestnika</h3>

                                    <div class="row g-3 mb-3">
                                        <div class="col-6">
                                            <label class="form-label small" for="first_name">Imię</label>
                                            <input type="text" name="first_name" id="first_name" class="form-control" required
                                                   value="<?= htmlspecialchars($_POST['first_name'] ?? $savedData['first_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small" for="last_name">Nazwisko</label>
                                            <input type="text" name="last_name" id="last_name" class="form-control" required
                                                   value="<?= htmlspecialchars($_POST['last_name'] ?? $savedData['last_name'] ?? '') ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small" for="class_name">Klasa</label>
                                        <input type="text" name="class" id="class_name" class="form-control" placeholder="np. 3TI" pattern="[1-5][A-Za-z0-9]{0,2}" maxlength="3" required
                                               value="<?= htmlspecialchars($_POST['class'] ?? $savedData['class'] ?? '') ?>">
                                    </div>

                                    <?php if (!$isGuest): ?>
                                        <div class="form-check mb-4">
                                            <input class="form-check-input" type="checkbox" name="save_data" id="saveData" <?= $savedData ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="saveData">Zapisz moje dane na przyszłość</label>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info border-0 small">
                                            <i class="bi bi-incognito me-1"></i>Konto gościa nie zapisuje profilu ani historii. Nauczyciel zobaczy tylko dane potrzebne do sprawdzianu.
                                        </div>
                                    <?php endif; ?>
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" name="privacy_consent" id="privacyConsent" required>
                                        <label class="form-check-label small" for="privacyConsent">Wyrażam zgodę na przetwarzanie moich danych osobowych zgodnie z <a href="../pages/privacy.php" target="_blank">Polityką Prywatności</a> w celu rejestracji udziału w sprawdzianie.</label>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg fw-bold rounded-pill">
                                            <i class="bi bi-box-arrow-in-right me-2"></i>Dołącz do sprawdzianu
                                        </button>
                                    </div>

                                    <div class="text-center mt-3">
                                        <a href="../index.php" class="text-muted small">Wróć do panelu</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js" integrity="sha384-9Q0jWoineiIq95JeIyBsNV90KKLfDsbkj29k/YFxf76a2JwkHDYkMuSbNGN6XJfV" crossorigin="anonymous"></script>
    <script>
    (function() {
        const accessCodeInput = document.getElementById('access_code');
        const scanButton = document.getElementById('scanQrButton');
        const stopScanButton = document.getElementById('stopScanButton');
        const fillCodeButton = document.getElementById('fillCodeButton');
        const qrPanel = document.getElementById('qrScanPanel');
        const qrVideo = document.getElementById('qrVideo');
        const qrStatus = document.getElementById('qrScanStatus');

        let stream = null;
        let detector = null;
        let scanning = false;
        let lastScan = '';
        const scanCanvas = document.createElement('canvas');
        const scanContext = scanCanvas.getContext('2d', { willReadFrequently: true });

        function normalizeCode(value) {
            return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 20);
        }

        function extractAccessCode(rawValue) {
            const value = String(rawValue || '').trim();
            if (!value) return '';

            try {
                const url = new URL(value, window.location.href);
                const paramCode = url.searchParams.get('code') || url.searchParams.get('access_code');
                if (paramCode) return normalizeCode(paramCode);
            } catch (err) {
                // Plain QR payloads are handled below.
            }

            const allowedSix = value.toUpperCase().match(/\b([ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6})\b/);
            if (allowedSix) return normalizeCode(allowedSix[1]);

            const fallback = normalizeCode(value);
            return fallback.length >= 4 ? fallback : '';
        }

        function setScannedCode(code) {
            lastScan = code;
            accessCodeInput.value = code;
            accessCodeInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        async function startScanner() {
            if (scanning) return;
            qrPanel.classList.add('active');

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                qrStatus.textContent = 'Ta przeglądarka nie udostępnia aparatu. Wpisz kod ręcznie.';
                return;
            }

            try {
                detector = window.BarcodeDetector ? new BarcodeDetector({ formats: ['qr_code'] }) : null;
            } catch (err) {
                detector = null;
            }

            if (!detector && typeof window.jsQR !== 'function') {
                qrStatus.textContent = 'Nie udało się załadować skanera QR. Odśwież stronę albo wpisz kod ręcznie.';
                return;
            }

            qrStatus.textContent = 'Uruchamianie aparatu...';

            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                });
                qrVideo.srcObject = stream;
                await qrVideo.play();
                scanning = true;
                scanLoop();
            } catch (err) {
                qrStatus.textContent = 'Nie udało się uruchomić aparatu: ' + (err.message || err);
                stopScanner();
            }
        }

        function detectWithJsQr() {
            if (!scanContext || typeof window.jsQR !== 'function' || !qrVideo.videoWidth || !qrVideo.videoHeight) {
                return '';
            }
            scanCanvas.width = qrVideo.videoWidth;
            scanCanvas.height = qrVideo.videoHeight;
            scanContext.drawImage(qrVideo, 0, 0, scanCanvas.width, scanCanvas.height);
            const frame = scanContext.getImageData(0, 0, scanCanvas.width, scanCanvas.height);
            const result = window.jsQR(frame.data, frame.width, frame.height);
            return result && result.data ? result.data : '';
        }

        async function scanLoop() {
            if (!scanning) return;

            try {
                let rawValue = '';
                if (detector) {
                    const results = await detector.detect(qrVideo);
                    rawValue = results && results.length > 0 ? (results[0].rawValue || '') : '';
                }
                if (!rawValue) {
                    rawValue = detectWithJsQr();
                }

                const code = extractAccessCode(rawValue);
                if (code) {
                    setScannedCode(code);
                    stopScanner('Kod wpisany automatycznie: ' + code);
                    return;
                }
                qrStatus.textContent = 'Skanowanie... skieruj kamerę na kod QR.';
            } catch (err) {
                qrStatus.textContent = 'Błąd podczas skanowania: ' + (err.message || err);
            }

            setTimeout(scanLoop, 450);
        }

        function stopScanner(message) {
            scanning = false;
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            if (qrVideo) {
                qrVideo.srcObject = null;
            }
            qrStatus.textContent = message || 'Skanowanie zatrzymane. Możesz ponownie uruchomić lub wpisać kod ręcznie.';
        }

        scanButton?.addEventListener('click', startScanner);
        stopScanButton?.addEventListener('click', () => stopScanner());
        fillCodeButton.addEventListener('click', function() {
            if (lastScan) {
                setScannedCode(lastScan);
                qrStatus.textContent = 'Kod został wpisany automatycznie.';
            } else {
                qrStatus.textContent = 'Najpierw zeskanuj kod QR.';
            }
        });
        accessCodeInput.addEventListener('input', function() {
            const normalized = normalizeCode(accessCodeInput.value);
            if (accessCodeInput.value !== normalized) {
                accessCodeInput.value = normalized;
            }
        });

        const initialCode = extractAccessCode(accessCodeInput.value);
        if (initialCode) accessCodeInput.value = initialCode;
    })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
