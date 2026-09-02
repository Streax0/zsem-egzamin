<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

// Only teachers and admins allowed
if (!in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'dyrektor'])) {
    setSessionMessage('error', 'Brak uprawnień do tej strony.');
    redirect('../index.php');
}

$userId = $_SESSION['user_id'];
$flash = getSessionMessage();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setSessionMessage('error', 'Błąd bezpieczeństwa (CSRF).');
        redirect('requests.php');
    }

    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($subject === '' || $message === '') {
        setSessionMessage('error', 'Proszę podać temat i treść wniosku.');
        redirect('requests.php');
    }
    if (mb_strlen($subject, 'UTF-8') > 180 || mb_strlen($message, 'UTF-8') > 4000 || containsProfanity($subject) || containsProfanity($message)) {
        setSessionMessage('error', 'Wniosek jest za długi albo zawiera niedozwolone słowa.');
        redirect('requests.php');
    }

    $reqId = createAdminRequest($pdo, $userId, $subject, $message);
    if ($reqId) {
        // Notify all admins
        try {
            $stmt = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'dyrektor')");
            $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $adminId) {
                addNotification($pdo, $adminId, 'admin_request', 'Nowy wniosek od ' . ($_SESSION['username'] ?? 'Nauczyciel'), 'admin/requests.php');
            }
        } catch (PDOException $e) {
            error_log('Notify admins error: ' . $e->getMessage());
        }

        setSessionMessage('success', 'Wniosek został wysłany do administracji.');
    } else {
        setSessionMessage('error', 'Nie udało się wysłać wniosku.');
    }

    redirect('requests.php');
}

$requests = getAdminRequestsForTeacher($pdo, $userId);
?>
<?php
$pageTitle = 'Wnioski do administracji';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>.small-msg{white-space:pre-wrap}</style>
HTML;
include '../includes/header.php';
?>

    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="fw-bold mb-1"><i class="bi bi-send-fill me-2 text-success"></i>Wnioski do administracji</h2>
                            <p class="text-muted">Wyślij prośbę lub zgłoszenie do administratora systemu.</p>
                        </div>
                    </div>

                    <?php if ($flash): ?>
                        <div class="alert alert-<?= ($flash['type'] === 'error') ? 'danger' : 'success'; ?> border-0 shadow-sm mb-4">
                            <?= htmlspecialchars($flash['message']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="dashboard-panel p-4">
                                <h5 class="mb-3">Nowy wniosek</h5>
                                <form method="POST">
                                    <?php echo csrfTokenField(); ?>
                                    <div class="mb-3">
                                        <label class="form-label">Temat</label>
                                        <input type="text" name="subject" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0">Treść</label>
                                            <small class="text-muted"><span id="msgWordCount">0</span>/850 słów</small>
                                        </div>
                                        <textarea name="message" class="form-control" rows="6" style="resize: none;" oninput="let text=this.value.trim(); let w=text?text.split(/\s+/):[]; if(w.length>850){w=w.slice(0,850);this.value=w.join(' ')+' ';} document.getElementById('msgWordCount').innerText=w.length;" required></textarea>
                                    </div>
                                    <button class="btn btn-primary">Wyślij wniosek</button>
                                </form>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="dashboard-panel p-4">
                                <h5 class="mb-3">Moje wnioski</h5>
                                <?php if (empty($requests)): ?>
                                    <div class="text-muted">Brak wysłanych wniosków.</div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($requests as $r): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <div class="fw-bold"><?= htmlspecialchars($r['subject']); ?></div>
                                                        <div class="text-muted small">Wysłano: <?= htmlspecialchars($r['created_at']); ?> • Status: <span class="badge bg-<?= $r['status'] === 'replied' ? 'success' : ($r['status'] === 'read' ? 'secondary' : 'primary'); ?> bg-opacity-10 text-<?= $r['status'] === 'replied' ? 'success' : ($r['status'] === 'read' ? 'secondary' : 'primary'); ?>"><?= htmlspecialchars($r['status']); ?></span></div>
                                                    </div>
                                                </div>
                                                <div class="pt-2 small-msg"><?= nl2br(htmlspecialchars($r['message'])); ?></div>
                                                <?php if (!empty($r['admin_reply'])): ?>
                                                    <div class="mt-2 p-3 bg-light rounded small"><strong>Odpowiedź:</strong><div class="small-msg mt-1"><?= nl2br(htmlspecialchars($r['admin_reply'])); ?></div></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
