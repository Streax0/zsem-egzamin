<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

startSecureSession();

// Legacy data/abuse_reports JSON storage is replaced by the abuse_reports database table.
$errors = [];
$successRef = null;
$contentUrl = '';
$description = '';
$reportType = 'illegal_content';
$reporterEmail = '';

$loggedReporter = isset($_SESSION['user_id']) ? ($_SESSION['username'] ?? ('ID #' . (int)$_SESSION['user_id'])) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '', 'report_abuse')) {
        $errors[] = 'Nieprawidłowe zabezpieczenie formularza. Odśwież stronę i spróbuj ponownie.';
    }

    $contentUrl = trim($_POST['content_url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $reportType = trim($_POST['report_type'] ?? 'illegal_content');
    $reporterEmail = trim($_POST['reporter_email'] ?? '');
    $goodFaith = ($_POST['good_faith'] ?? '') === '1';
    $website = trim($_POST['website'] ?? '');

    if ($website !== '') {
        $errors[] = 'Zgłoszenie wygląda na automatyczne.';
    }
    if ($contentUrl !== '' && mb_strlen($contentUrl) > 500) {
        $errors[] = 'URL albo identyfikator treści może mieć maksymalnie 500 znaków.';
    }
    if ($description === '' || mb_strlen($description, 'UTF-8') < 10 || countWordsUtf8($description) > 120) {
        $errors[] = 'Opis jest wymagany, musi być konkretny i może mieć maksymalnie 120 słów.';
    }
    if ($reporterEmail !== '' && !filter_var($reporterEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Podaj poprawny adres e-mail albo zostaw pole puste.';
    }
    if (!$goodFaith) {
        $errors[] = 'Potwierdź oświadczenie o dobrej wierze.';
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
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Formularz zgłaszania naruszeń i nielegalnych treści w ZSEM Tech.">
    <title>Zgłoś naruszenie – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css')); ?>">
    <style>
        body { background: #f8fafc; color: #1e293b; font-family: 'Inter', sans-serif; }
        .legal-wrap { max-width: 820px; margin: 3rem auto; padding: 0 1rem 4rem; }
        .legal-hero { background: linear-gradient(135deg, #991b1b, #ea580c); color: #fff; border-radius: 1.25rem; padding: 2.5rem; margin-bottom: 2rem; }
        .legal-card { background: #fff; border-radius: 1rem; padding: 2rem; box-shadow: 0 4px 24px rgba(0,0,0,.06); }
        textarea { resize: none; }
        .preset-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:.75rem; }
        .preset-option { border:1px solid #e2e8f0; border-radius:1rem; padding:.85rem; cursor:pointer; }
        .btn-check:checked + .preset-option { border-color:#dc2626; background:#fff1f2; box-shadow:0 0 0 4px rgba(220,38,38,.08); }
        body.dark-mode .legal-card { background: #1e293b !important; color: #e5e7eb !important; border: 1px solid rgba(148,163,184,.24); box-shadow: none; }
        body.dark-mode .legal-card p, body.dark-mode .legal-card li, body.dark-mode .legal-card label { color: #cbd5e1 !important; }
    </style>
</head>
<body>
    <main class="legal-wrap" role="main">
        <a href="index.php" class="btn btn-light mb-4 rounded-pill shadow-sm">Powrót</a>
        <section class="legal-hero" aria-labelledby="report-title">
            <p class="fw-bold mb-2">DSA / bezpieczeństwo społeczności</p>
            <h1 id="report-title" class="fw-800">Zgłoś naruszenie</h1>
            <p class="mb-0">Użyj formularza do zgłoszenia treści naruszającej prawo, regulamin albo prywatność.</p>
            <?php if ($loggedReporter): ?>
                <p class="mb-0 mt-2 small opacity-75">Zgłaszasz jako: <strong><?= htmlspecialchars($loggedReporter) ?></strong></p>
            <?php endif; ?>
        </section>

        <section class="legal-card">
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
    </main>
    <?php include __DIR__ . '/includes/cookie_consent.php'; ?>
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
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
</body>
</html>
