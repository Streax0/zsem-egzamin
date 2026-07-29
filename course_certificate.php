<?php
declare(strict_types=1);

require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/CourseService.php';

startSecureSession();
requireLogin();

$userId = (int)$_SESSION['user_id'];
$courseId = (int)($_GET['course_id'] ?? 0);

$course = courseFetchById($pdo, $courseId);
if (!$course || (int)($course['has_certificate'] ?? 1) !== 1) {
    setSessionMessage('error', 'Dla tego kursu nie udostępniono certyfikatu.');
    redirect('courses.php');
}

// Fetch user data
$uStmt = $pdo->prepare('SELECT id, first_name, last_name, username, email FROM users WHERE id = ? LIMIT 1');
$uStmt->execute([$userId]);
$user = $uStmt->fetch(PDO::FETCH_ASSOC);

$userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if ($userName === '') {
    $userName = (string)($user['username'] ?? 'Uczestnik');
}

// Check completion & test/exam requirement
$structure = courseFetchStructure($pdo, $courseId, false);
$items = courseItemsInOrder($structure);
$totalItems = count($items);

$completed = 0;
$allExamsPassed = true;

if ($totalItems > 0) {
    $pStmt = $pdo->prepare("SELECT COUNT(*) FROM user_course_progress WHERE user_id = ? AND course_id = ? AND status = 'completed'");
    $pStmt->execute([$userId, $courseId]);
    $completed = (int)$pStmt->fetchColumn();

    foreach ($items as $it) {
        if (in_array($it['type'], ['quiz', 'exam'], true)) {
            $eStmt = $pdo->prepare("SELECT status FROM user_course_progress WHERE user_id = ? AND item_id = ? LIMIT 1");
            $eStmt->execute([$userId, (int)$it['id']]);
            $st = $eStmt->fetchColumn();
            if ($st !== 'completed') {
                $allExamsPassed = false;
                break;
            }
        }
    }
}

$progressPercent = $totalItems > 0 ? (int)round(($completed / $totalItems) * 100) : 0;
$isAdminPreview = roleHasAdminAccess((string)($_SESSION['role'] ?? 'user'));

if (($progressPercent < 100 || !$allExamsPassed) && !$isAdminPreview) {
    setSessionMessage('error', 'Certyfikat wymaga ukończenia 100% kursu oraz zaliczenia testu / egzaminu końcowego.');
    redirect('course_view.php?id=' . $courseId);
}

// Safely issue & persist certificate in user_certificates table
$certCode = courseIssueCertificate($pdo, $userId, $courseId);
if (!$certCode) {
    $certHash = strtoupper(substr(hash('sha256', 'ZSEM_CERT_' . $userId . '_' . $courseId), 0, 10));
    $certCode = 'ZSEM-CERT-' . $certHash;
}

$pageTitle = 'Certyfikat Ukończenia: ' . htmlspecialchars($course['title']) . ' — ZSEM Tech';
$base_url = '';
include 'includes/header.php';
?>

<!-- Google Fonts for Executive Certificate -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800;900&family=Great+Vibes&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
.cert-page-wrap {
    background: #0f172a;
    min-height: calc(100vh - 65px);
    padding: 2.5rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.cert-container {
    width: 100%;
    max-width: 980px;
    background: #ffffff;
    border-radius: 1.25rem;
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.4);
    position: relative;
    overflow: hidden;
    padding: 2.5rem;
    border: 12px solid #1e293b;
    box-sizing: border-box;
}

.cert-gold-frame {
    border: 3px double #d97706;
    padding: 2.5rem 2rem;
    position: relative;
    border-radius: 0.5rem;
    background: radial-gradient(circle at center, #ffffff 60%, #fffdfa 100%);
}

.cert-corner-decor {
    position: absolute;
    width: 32px;
    height: 32px;
    border: 4px solid #b45309;
}
.cert-corner-tl { top: -4px; left: -4px; border-right: 0; border-bottom: 0; }
.cert-corner-tr { top: -4px; right: -4px; border-left: 0; border-bottom: 0; }
.cert-corner-bl { bottom: -4px; left: -4px; border-right: 0; border-top: 0; }
.cert-corner-br { bottom: -4px; right: -4px; border-left: 0; border-top: 0; }

.cert-watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 20rem;
    color: rgba(217, 119, 6, 0.03);
    pointer-events: none;
    user-select: none;
    z-index: 0;
}

.cert-header {
    text-align: center;
    position: relative;
    z-index: 1;
}

.cert-brand {
    font-family: 'Montserrat', sans-serif;
    font-size: 0.95rem;
    font-weight: 800;
    letter-spacing: 0.25em;
    color: #b45309;
    text-transform: uppercase;
    margin-bottom: 0.75rem;
}

.cert-title {
    font-family: 'Cinzel', serif;
    font-size: clamp(2rem, 4vw, 2.75rem);
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #0f172a;
    text-transform: uppercase;
    margin-bottom: 1.5rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.cert-subtitle {
    font-family: 'Montserrat', sans-serif;
    font-size: 0.88rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-weight: 600;
    margin-bottom: 1rem;
}

.cert-recipient {
    font-family: 'Cinzel', serif;
    font-size: clamp(1.4rem, 5vw, 3.2rem);
    font-weight: 900;
    color: #0f172a;
    border-bottom: 2px solid #d97706;
    display: inline-block;
    padding: 0 1rem 0.4rem;
    margin-bottom: 1.75rem;
    letter-spacing: 0.02em;
    max-width: 100%;
    word-break: break-word;
    box-sizing: border-box;
}

.cert-statement {
    font-family: 'Montserrat', sans-serif;
    font-size: 1.05rem;
    color: #334155;
    max-width: 700px;
    margin: 0 auto 2rem;
    line-height: 1.7;
    font-weight: 500;
}

.cert-course-card {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(217, 119, 6, 0.04) 100%);
    border: 1px solid rgba(217, 119, 6, 0.3);
    border-radius: 0.75rem;
    padding: 0.85rem 1.75rem;
    display: inline-block;
    margin-top: 0.5rem;
    max-width: 100%;
    box-sizing: border-box;
}

.cert-course-title {
    font-family: 'Montserrat', sans-serif;
    font-size: clamp(1rem, 3vw, 1.5rem);
    font-weight: 800;
    color: #0f172a;
    display: block;
    word-break: break-word;
}

.cert-footer {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 2.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
    position: relative;
    z-index: 1;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.cert-code-box {
    font-family: 'JetBrains Mono', 'Consolas', monospace;
    font-size: 0.78rem;
    color: #475569;
    background: #f8fafc;
    padding: 0.4rem 0.75rem;
    border-radius: 0.4rem;
    border: 1px solid #cbd5e1;
    font-weight: 600;
    word-break: break-all;
}

/* Gold Foil Embossed Stamp */
.cert-gold-seal {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    background: linear-gradient(135deg, #fef3c7 0%, #f59e0b 50%, #b45309 100%);
    color: #ffffff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 25px rgba(217, 119, 6, 0.4), inset 0 0 0 3px rgba(255, 255, 255, 0.6);
    font-family: 'Montserrat', sans-serif;
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    text-align: center;
    border: 4px solid #ffffff;
    position: relative;
    flex-shrink: 0;
}

.cert-gold-seal i {
    font-size: 1.75rem;
    margin-bottom: 0.1rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

.cert-signature-line {
    font-family: 'Great Vibes', cursive;
    font-size: 2rem;
    color: #0f172a;
    border-bottom: 1px solid #0f172a;
    padding-bottom: 0.2rem;
    margin-bottom: 0.3rem;
    width: 180px;
    text-align: center;
}

.btn-linkedin {
    background: #0a66c2 !important;
    color: #ffffff !important;
    border: none !important;
    font-weight: 700 !important;
    box-shadow: 0 4px 14px rgba(10, 102, 194, 0.35) !important;
    transition: all 0.2s ease !important;
}
.btn-linkedin:hover {
    background: #004182 !important;
    color: #ffffff !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(10, 102, 194, 0.45) !important;
}

/* ── Mobile: Tablets ── */
@media (max-width: 768px) {
    .cert-page-wrap {
        padding: 1.5rem 0.5rem;
    }
    .cert-container {
        padding: 1.25rem;
        border-width: 6px;
        border-radius: 0.75rem;
    }
    .cert-gold-frame {
        padding: 1.5rem 1rem;
    }
    .cert-corner-decor {
        width: 20px;
        height: 20px;
        border-width: 3px;
    }
    .cert-watermark {
        font-size: 10rem;
    }
    .cert-brand {
        font-size: 0.75rem;
        letter-spacing: 0.15em;
    }
    .cert-title {
        font-size: clamp(1.4rem, 5vw, 2rem);
        margin-bottom: 1rem;
        letter-spacing: 0.04em;
    }
    .cert-subtitle {
        font-size: 0.75rem;
        letter-spacing: 0.1em;
    }
    .cert-statement {
        font-size: 0.9rem;
        margin-bottom: 1.25rem;
    }
    .cert-course-card {
        padding: 0.6rem 1rem;
    }
    .cert-footer {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 1.25rem;
    }
    .cert-footer > div:last-child {
        text-align: center;
    }
    .cert-signature-line {
        font-size: 1.6rem;
        width: 160px;
        margin-left: auto;
        margin-right: auto;
    }
    .cert-gold-seal {
        width: 80px;
        height: 80px;
        font-size: 0.55rem;
    }
    .cert-gold-seal i {
        font-size: 1.4rem;
    }
}

/* ── Mobile: Small phones ── */
@media (max-width: 480px) {
    .cert-page-wrap {
        padding: 1rem 0.25rem;
    }
    .cert-container {
        padding: 0.75rem;
        border-width: 4px;
        border-radius: 0.5rem;
    }
    .cert-gold-frame {
        padding: 1rem 0.65rem;
    }
    .cert-corner-decor {
        width: 14px;
        height: 14px;
        border-width: 2px;
    }
    .cert-watermark {
        font-size: 6rem;
    }
    .cert-brand {
        font-size: 0.6rem;
        letter-spacing: 0.1em;
    }
    .cert-title {
        font-size: 1.15rem;
        margin-bottom: 0.75rem;
    }
    .cert-subtitle {
        font-size: 0.65rem;
        letter-spacing: 0.08em;
    }
    .cert-recipient {
        padding: 0 0.5rem 0.3rem;
    }
    .cert-statement {
        font-size: 0.8rem;
        line-height: 1.5;
        margin-bottom: 1rem;
    }
    .cert-course-card {
        padding: 0.5rem 0.75rem;
    }
    .cert-course-title {
        font-size: 0.9rem;
    }
    .cert-footer {
        margin-top: 1.25rem;
        padding-top: 1rem;
        gap: 1rem;
    }
    .cert-gold-seal {
        width: 64px;
        height: 64px;
        font-size: 0.5rem;
        border-width: 3px;
    }
    .cert-gold-seal i {
        font-size: 1.1rem;
    }
    .cert-signature-line {
        font-size: 1.3rem;
        width: 130px;
    }
    .cert-code-box {
        font-size: 0.65rem;
        padding: 0.3rem 0.5rem;
    }
    .cert-actions {
        flex-direction: column;
        align-items: stretch;
    }
    .cert-actions .btn {
        width: 100%;
        text-align: center;
    }
}

/* ── Print styles ── */
@media print {
    @page {
        size: A4 landscape;
        margin: 10mm;
    }

    /* Force print backgrounds in all browsers */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    /* Hide everything non-certificate */
    body {
        background: #ffffff !important;
        color: #000000 !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .main-header, .main-footer, .top-header, .sidebar,
    .cert-actions, .cookie-consent-banner,
    nav, footer, .navbar, .toast-container,
    .offcanvas, .modal, .breadcrumb {
        display: none !important;
    }

    /* Page wrapper — fill the page */
    .cert-page-wrap {
        padding: 0 !important;
        margin: 0 !important;
        background: none !important;
        min-height: auto !important;
        display: block !important;
    }

    /* Certificate container — fill available space */
    .cert-container {
        max-width: 100% !important;
        width: 100% !important;
        margin: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        border: 4px solid #1e293b !important;
        padding: 1.5rem !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .cert-gold-frame {
        border: 3px double #d97706 !important;
        background: radial-gradient(circle at center, #ffffff 60%, #fffdfa 100%) !important;
        padding: 2rem 1.5rem !important;
    }

    /* Ensure corner decorations print */
    .cert-corner-decor {
        border-color: #b45309 !important;
    }

    /* Watermark visibility */
    .cert-watermark {
        color: rgba(217, 119, 6, 0.03) !important;
        font-size: 16rem !important;
    }

    /* Preserve gold seal gradient */
    .cert-gold-seal {
        background: linear-gradient(135deg, #fef3c7 0%, #f59e0b 50%, #b45309 100%) !important;
        box-shadow: none !important;
        border: 4px solid #e2e8f0 !important;
    }

    /* Course card background */
    .cert-course-card {
        background: rgba(245, 158, 11, 0.08) !important;
        border: 1px solid rgba(217, 119, 6, 0.3) !important;
    }

    /* Code box background */
    .cert-code-box {
        background: #f8fafc !important;
        border: 1px solid #cbd5e1 !important;
    }

    /* Footer layout for print */
    .cert-footer {
        flex-wrap: nowrap !important;
        flex-direction: row !important;
        gap: 1rem !important;
    }

    /* Prevent orphaned sections */
    .cert-header, .cert-footer {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
}
</style>

<?php
$linkedInCertName = rawurlencode('Certyfikat: ' . (string)$course['title']);
$linkedInOrg = rawurlencode('ZSEM Tech Platforma Edukacyjna');
$linkedInYear = date('Y');
$linkedInMonth = date('n');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$currentUrl = $scheme . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '');
$linkedInCertUrl = rawurlencode($currentUrl);
$linkedInCertId = rawurlencode($certCode);
$linkedInAddUrl = "https://www.linkedin.com/profile/add?startTask=CERTIFICATION_NAME&name={$linkedInCertName}&organizationName={$linkedInOrg}&issueYear={$linkedInYear}&issueMonth={$linkedInMonth}&certUrl={$linkedInCertUrl}&certId={$linkedInCertId}";
?>

<div class="cert-page-wrap">
    
    <!-- Print / Action Controls -->
    <div class="cert-actions mb-4 d-flex flex-wrap gap-3">
        <a href="course_view.php?id=<?php echo $courseId; ?>" class="btn btn-outline-light rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i> Wróć do kursu
        </a>
        <a href="<?php echo $linkedInAddUrl; ?>" target="_blank" rel="noopener noreferrer" class="btn btn-linkedin rounded-pill px-4 shadow">
            <i class="bi bi-linkedin me-2"></i> Dodaj do profilu LinkedIn
        </a>
        <button type="button" class="btn btn-warning btn-gold-cert rounded-pill px-4 shadow" onclick="window.print();">
            <i class="bi bi-printer-fill me-1"></i> Drukuj / Pobierz Certyfikat (PDF)
        </button>
    </div>

    <!-- Official Certificate Document -->
    <div class="cert-container">
        <i class="bi bi-award-fill cert-watermark"></i>

        <div class="cert-gold-frame">
            <div class="cert-corner-decor cert-corner-tl"></div>
            <div class="cert-corner-decor cert-corner-tr"></div>
            <div class="cert-corner-decor cert-corner-bl"></div>
            <div class="cert-corner-decor cert-corner-br"></div>

            <div class="cert-header">
                <div class="cert-brand">
                    <i class="bi bi-shield-check me-1"></i> ZSEM Tech Platforma Edukacyjna
                </div>
                <h1 class="cert-title">Certyfikat Ukończenia</h1>

                <div class="cert-subtitle">Niniejszym zaświadcza się, że</div>

                <div class="cert-recipient">
                    <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>
                </div>

                <div class="cert-statement">
                    pomyślnie ukończył(a) pełen program szkoleniowy i spełnił(a) wszystkie wymogi weryfikacyjne w kursie:
                    <div class="cert-course-card mt-2">
                        <span class="cert-course-title"><?php echo htmlspecialchars((string)$course['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </div>

            <div class="cert-footer">
                <div>
                    <div class="small fw-bold text-dark mb-1">Data wystawienia:</div>
                    <div class="small text-muted mb-2"><?php echo date('d.m.Y'); ?></div>
                    <a href="verify_certificate.php?code=<?php echo urlencode($certCode); ?>" target="_blank" class="cert-code-box text-decoration-none d-inline-block">
                        <i class="bi bi-patch-check-fill me-1 text-primary"></i> Weryfikacja ID: <strong><?php echo htmlspecialchars($certCode, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </a>
                </div>

                <div class="cert-gold-seal">
                    <i class="bi bi-patch-check-fill"></i>
                    <span>ZSEM TECH</span>
                </div>

                <div class="text-end">
                    <div class="cert-signature-line">
                        ZSEM Tech Board
                    </div>
                    <div class="small text-muted fw-bold">Podpis Cyfrowy Platformy</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
