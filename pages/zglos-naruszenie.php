<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
$showSidebar = true;

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

// Legacy data/abuse_reports JSON storage reference


$errors = [];
$successRef = null;
$contentUrl = '';
$description = '';
$reportType = 'illegal_content';
$reporterEmail = '';

$loggedReporter = isset($_SESSION['user_id']) ? ($_SESSION['username'] ?? ('ID #' . (int)$_SESSION['user_id'])) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    securityValidateRequestCsrf('report_abuse');
    securityThrottle('abuse_reports:submit', 3, 60);

    $reportType = securityInputEnum($_POST['report_type'] ?? '', ['illegal_content', 'privacy', 'abuse', 'copyright', 'other'], 'other');
    $contentUrl = trim($_POST['content_url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $reporterEmail = trim($_POST['reporter_email'] ?? '');
    $honey = $_POST['website'] ?? '';

    if ($honey !== '') {
        $errors[] = 'Wykryto bota.';
    }
    if ($description === '') {
        $errors[] = 'Opis problemu jest wymagany.';
    }
    if ((preg_match_all('/[\p{L}\p{N}]+/gu', $description)) > 120) {
        $errors[] = 'Opis problemu może mieć maksymalnie 120 słów.';
    }

    if (!$errors) {
        $result = createAbuseReport($pdo, [
            'report_type' => $reportType,
            'content_url' => $contentUrl,
            'description' => $description,
            'email' => $reporterEmail,
        ]);
        if ($result['ok']) {
            $successRef = 'ABUSE-' . (int)$result['id'];
            $contentUrl = $description = $reporterEmail = '';
            $reportType = 'illegal_content';
        } else {
            $errors[] = $result['message'] ?? 'Nie udało się zapisać zgłoszenia.';
        }
    }
}
?>
<?php
$pageTitle = 'Zgłoś naruszenie – ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
        textarea { resize: none; }
        .preset-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:.75rem; }
        .preset-option { border:1px solid var(--border-color); border-radius:1rem; padding:.85rem; cursor:pointer; background: var(--panel-bg); }
        .btn-check:checked + .preset-option { border-color:#dc2626; background:#fff1f2; color:#dc2626; box-shadow:0 0 0 4px rgba(220,38,38,.08); }
        body.dark-mode .btn-check:checked + .preset-option { background:#450a0a; color:#fca5a5; }
    </style>
HTML;
$bodyAttributes = 'class="<?php echo htmlspecialchars($bodyClassStr); ?';
include '../includes/header.php';
?>">
<div class="dashboard-layout">
    <?php if ($showSidebar) include '../includes/sidebar.php'; ?>
    <div class="main-container" style="<?php echo !$showSidebar ? 'margin-left: 0;' : ''; ?>">
        <?php if ($showSidebar) include '../includes/topbar.php'; ?>
        <main role="main" class="content-body">
            <div class="container py-4" style="max-width: 960px;">
                <a href="../index.php" class="btn btn-light mb-4 rounded-pill shadow-sm"><i class="bi bi-arrow-left me-2"></i>Powrót</a>

                <section class="welcome-card dashboard-hero mb-4 animate-in" aria-labelledby="report-title" style="overflow: hidden;">
                    <div class="dashboard-hero-inner">
                        <div class="hero-left" style="text-shadow: 0 2px 8px rgba(15, 23, 42, 0.15);">
                            <div class="hero-rank-pill" style="border-color: rgba(255, 255, 255, 0.18); background: rgba(15, 23, 42, 0.35); color: #ffffff; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);">
                                <i class="bi bi-shield-exclamation"></i>
                                <span style="color: #ffffff; font-weight: 800;">DSA / Bezpieczeństwo</span>
                            </div>
                            <h1 id="report-title" class="h2" style="font-weight: 800; color: #ffffff;"><i class="bi bi-exclamation-triangle me-2"></i>Zgłoś naruszenie</h1>
                            <p class="mb-0 text-white" style="font-size: 1.05rem; line-height: 1.6; font-weight: 500; opacity: 0.95;">Użyj formularza do zgłoszenia treści naruszającej prawo, regulamin albo prywatność.</p>
                            <?php if ($loggedReporter): ?>
                                <p class="mb-0 mt-2 small text-white" style="opacity: 0.85; font-weight: 500;">Zgłaszasz jako: <strong><?= htmlspecialchars($loggedReporter) ?></strong></p>
                            <?php endif; ?>
                        </div>
                        <div class="hero-right d-none d-lg-flex align-items-center justify-content-end">
                            <i class="bi bi-shield-fill-exclamation text-white" style="font-size: 5.5rem; opacity: 0.22; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.15));"></i>
                        </div>
                    </div>
                </section>

                <section class="dashboard-panel mb-4">
                    <?php if ($successRef): ?>
                        <div class="alert alert-success" role="status">
                            Zgłoszenie zapisane. Numer referencyjny: <strong><?= htmlspecialchars($successRef) ?></strong>.
                        </div>
                    <?php endif; ?>
                    <?php if ($errors): ?>
                        <div class="alert alert-danger" role="alert">
                            <strong>Popraw formularz:</strong>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" novalidate>
                        <?= csrfTokenField('report_abuse'); ?>
                        <input type="text" name="website" tabindex="-1" autocomplete="off" class="visually-hidden" aria-hidden="true">
                        <div class="mb-3">
                            <label class="form-label">Kategoria zgłoszenia *</label>
                            <div class="preset-grid">
                                <?php
                                $types = [
                                    'illegal_content' => ['Treść bezprawna', 'Groźby, nawoływanie, treści zakazane.'],
                                    'privacy' => ['Prywatność', 'Dane osobowe, wizerunek, podszywanie.'],
                                    'abuse' => ['Nękanie', 'Obraźliwe lub szkodliwe zachowanie.'],
                                    'copyright' => ['Prawa autorskie', 'Nieuprawnione użycie materiałów.'],
                                    'other' => ['Inne', 'Pozostałe naruszenia regulaminu.'],
                                ];
                                foreach ($types as $key => [$label, $desc]):
                                ?>
                                    <div>
                                        <input class="btn-check" type="radio" name="report_type" id="type_<?php echo $key; ?>" value="<?php echo $key; ?>" <?php echo $reportType === $key ? 'checked' : ''; ?>>
                                        <label class="preset-option d-block h-100" for="type_<?php echo $key; ?>">
                                            <strong><?php echo htmlspecialchars($label); ?></strong>
                                            <span class="d-block small text-muted"><?php echo htmlspecialchars($desc); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="content_url" class="form-label">URL albo identyfikator treści</label>
                            <input type="text" class="form-control" id="content_url" name="content_url" maxlength="500" value="<?= htmlspecialchars($contentUrl) ?>" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Opis problemu *</label>
                            <textarea class="form-control" id="description" name="description" rows="5" maxlength="1200" required aria-describedby="wordHelp"><?= htmlspecialchars($description) ?></textarea>
                            <div class="form-text" id="wordHelp"><span id="wordCount">0</span>/120 słów</div>
                        </div>
                        <div class="mb-3">
                            <label for="reporter_email" class="form-label">E-mail zgłaszającego</label>
                            <input type="email" class="form-control" id="reporter_email" name="reporter_email" maxlength="160" value="<?= htmlspecialchars($reporterEmail) ?>" autocomplete="email">
                        </div>
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" value="1" id="good_faith" name="good_faith" required>
                            <label class="form-check-label" for="good_faith">Oświadczam, że zgłoszenie składam w dobrej wierze.</label>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Wyślij zgłoszenie</button>
                    </form>
                </section>
            </div>
        </main>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/cookie_consent.php'; ?>
<script>
const description = document.getElementById('description');
const wordCount = document.getElementById('wordCount');
const syncWords = () => {
    const words = (description.value.trim().match(/[\p{L}\p{N}]+/gu) || []).length;
    wordCount.textContent = words;
    wordCount.classList.toggle('text-danger', words > 120);
};
description?.addEventListener('input', syncWords);
syncWords();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

