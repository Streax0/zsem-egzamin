<?php
declare(strict_types=1);

require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

startSecureSession();

$code = trim((string)($_GET['code'] ?? ''));
$certData = null;
$errorMsg = null;

if ($code !== '') {
    $statement = $pdo->prepare('
        SELECT uc.*, u.first_name, u.last_name, u.username, c.title AS course_title
        FROM user_certificates uc
        JOIN users u ON u.id = uc.user_id
        LEFT JOIN courses c ON c.id = uc.course_id
        WHERE uc.certificate_code = ?
        LIMIT 1
    ');
    $statement->execute([$code]);
    $certData = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$certData) {
        $errorMsg = 'Nie znaleziono oficjalnego certyfikatu o podanym identyfikatorze. Sprawdź poprawność wprowadzonego kodu.';
    }
}

$pageTitle = 'Weryfikacja Certyfikatu — ZSEM Tech';
include 'includes/header.php';
?>

<div class="container py-5" style="max-width: 720px;">
    <div class="text-center mb-4">
        <span class="badge text-bg-primary px-3 py-2 text-uppercase mb-2" style="letter-spacing: 0.1em;">
            <i class="bi bi-shield-check me-1"></i> System Weryfikacji Autentyczności
        </span>
        <h1 class="h2 fw-bold">Weryfikacja Certyfikatu ZSEM Tech</h1>
        <p class="text-muted">Oficjalny portal potwierdzania autentyczności zaświadczeń i certyfikatów edukacyjnych.</p>
    </div>

    <!-- Search Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <form method="get" action="verify_certificate.php" class="row g-2">
                <div class="col-sm-9">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="code" class="form-control border-start-0 ps-0" placeholder="Wprowadź kod Np. ZSEM-CERT-25E779C09D" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                </div>
                <div class="col-sm-3">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Sprawdź</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger d-flex align-items-center gap-3 p-3 shadow-sm rounded-3">
            <i class="bi bi-exclamation-triangle-fill fs-2 flex-shrink-0 text-danger"></i>
            <div>
                <h2 class="h6 fw-bold mb-1">Błąd weryfikacji</h2>
                <div><?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($certData): ?>
        <?php
        $fullName = trim(($certData['first_name'] ?? '') . ' ' . ($certData['last_name'] ?? ''));
        if ($fullName === '') {
            $fullName = (string)($certData['username'] ?? 'Absolwent');
        }
        $courseTitle = !empty($certData['course_title']) ? $certData['course_title'] : $certData['name'];
        ?>
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="card-header bg-success text-white py-3 px-4 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-patch-check-fill fs-4"></i>
                    <strong class="fs-5">Certyfikat Autentyczny i Ważny</strong>
                </div>
                <span class="badge text-bg-light font-monospace"><?php echo htmlspecialchars((string)$certData['certificate_code'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="card-body p-4 p-md-5">
                <div class="mb-4">
                    <span class="small text-muted text-uppercase fw-bold">Wystawiono dla:</span>
                    <h3 class="h2 fw-bold text-dark mb-0 mt-1"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></h3>
                </div>

                <div class="mb-4">
                    <span class="small text-muted text-uppercase fw-bold">Program szkoleniowy:</span>
                    <h4 class="h4 fw-bold text-primary mt-1"><?php echo htmlspecialchars((string)$courseTitle, ENT_QUOTES, 'UTF-8'); ?></h4>
                </div>

                <div class="row g-3 py-3 border-top border-bottom mb-4">
                    <div class="col-6">
                        <span class="small text-muted text-uppercase d-block">Organizacja Wydająca:</span>
                        <strong class="text-dark"><?php echo htmlspecialchars((string)($certData['organization'] ?? 'ZSEM Tech'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="col-6">
                        <span class="small text-muted text-uppercase d-block">Data Wystawienia:</span>
                        <strong class="text-dark"><?php echo !empty($certData['obtained_date']) ? date('d.m.Y', strtotime((string)$certData['obtained_date'])) : '—'; ?></strong>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between">
                    <span class="small text-muted"><i class="bi bi-shield-lock me-1"></i> Cyfrowo zweryfikowano w bazie danych ZSEM Tech</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print();">
                        <i class="bi bi-printer me-1"></i> Drukuj Potwierdzenie
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<?php include 'includes/footer.php'; ?>
</body>
</html>
