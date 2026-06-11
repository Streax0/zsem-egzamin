<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();

if (!isAdmin($pdo, $_SESSION['user_id'])) {
    setSessionMessage('error', 'Brak uprawnień do panelu administracyjnego.');
    redirect('index.php');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '', 'admin_requests')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('admin_requests.php');
    }

    $action = $_POST['action'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);

    if ($action === 'reply_request' && $requestId > 0) {
        $reply = trim($_POST['reply_text'] ?? '');
        if ($reply === '') {
            setSessionMessage('error', 'Treść odpowiedzi nie może być pusta.');
        } else {
            if (replyAdminRequest($pdo, $requestId, $_SESSION['user_id'], $reply)) {
                setSessionMessage('success', 'Odpowiedź wysłana.');
            } else {
                setSessionMessage('error', 'Błąd podczas zapisu odpowiedzi.');
            }
        }
        redirect('admin_requests.php');
    }

    if ($action === 'mark_read' && $requestId > 0) {
        if (markRequestRead($pdo, $requestId, $_SESSION['user_id'])) {
            setSessionMessage('success', 'Wniosek oznaczono jako przeczytany.');
        } else {
            setSessionMessage('error', 'Błąd podczas aktualizacji.');
        }
        redirect('admin_requests.php');
    }

    if ($action === 'delete_request' && $requestId > 0) {
        if (deleteAdminRequest($pdo, $requestId, (int)$_SESSION['user_id'])) {
            setSessionMessage('success', 'Wniosek usunięty.');
        } else {
            setSessionMessage('error', 'Nie udało się usunąć wniosku.');
        }
        redirect('admin_requests.php');
    }

    if ($action === 'resolve_teacher_application' && $requestId > 0) {
        $decision = $_POST['decision'] ?? '';
        $note = trim((string)($_POST['decision_note'] ?? ''));
        if (resolveTeacherApplication($pdo, $requestId, (int)$_SESSION['user_id'], $decision, $note)) {
            setSessionMessage('success', $decision === 'approve' ? 'Aplikacja nauczyciela zaakceptowana.' : 'Aplikacja nauczyciela odrzucona.');
        } else {
            setSessionMessage('error', 'Nie udało się rozpatrzyć aplikacji.');
        }
        redirect('admin_requests.php');
    }
}

$requests = getAllAdminRequests($pdo);
$roleApplications = array_values(array_filter($requests, static fn($r) => ($r['type'] ?? 'general') === 'teacher_application'));
$generalRequests = array_values(array_filter($requests, static fn($r) => ($r['type'] ?? 'general') !== 'teacher_application'));
$flash = getSessionMessage();
?>
<!doctype html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wnioski - Panel Admina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include 'includes/topbar.php'; ?>
            <main role="main" class="content-body">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="fw-bold mb-1">Wnioski i aplikacje</h2>
                            <p class="text-muted">Osobno zgłoszenia o role oraz zwykłe wnioski do administracji.</p>
                        </div>
                    </div>

                    <?php if ($flash): ?>
                        <div class="alert alert-<?= ($flash['type'] === 'error') ? 'danger' : 'success'; ?> border-0 shadow-sm mb-4 rounded-4">
                            <?= htmlspecialchars($flash['message']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="dashboard-panel p-0 overflow-hidden mb-4 admin-requests-table-panel">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h5 class="fw-bold mb-1"><i class="bi bi-person-badge text-warning me-2"></i>Aplikacje ról</h5>
                                <div class="text-muted small">Aplikacje z rejestracji. Zawierają imię, nazwisko, login, powód i czas zgłoszenia.</div>
                            </div>
                            <span class="badge bg-warning text-dark rounded-pill px-3"><?php echo count($roleApplications); ?></span>
                        </div>
                        <?php if (empty($roleApplications)): ?>
                            <div class="p-4 text-muted">Brak aplikacji o role.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Imię i nazwisko</th>
                                        <th>Login / e-mail</th>
                                        <th>Powód</th>
                                        <th>Status</th>
                                        <th>Czas</th>
                                        <th class="text-end">Decyzja</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roleApplications as $r): ?>
                                    <?php
                                        $rUser = ['username' => $r['teacher_username'] ?? '', 'first_name' => $r['first_name'] ?? '', 'last_name' => $r['last_name'] ?? ''];
                                        $displayName = userDisplayName($rUser);
                                        $handle = userHandle($rUser);
                                    ?>
                                    <tr id="request-<?php echo (int)$r['id']; ?>">
                                        <td class="fw-bold" data-label="Imię i nazwisko">
                                            <?php echo htmlspecialchars($displayName); ?>
                                            <?php if (($r['trust_status'] ?? 'trusted') === 'untrusted'): ?>
                                                <div class="mt-1">
                                                    <span class="badge bg-danger rounded-pill">untrusted</span>
                                                    <span class="badge bg-warning text-dark rounded-pill">possible fraud / duplicate identity</span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted" data-label="Login / e-mail">
                                            <div><?php echo htmlspecialchars($handle); ?></div>
                                            <div><?php echo htmlspecialchars($r['email'] ?? ''); ?></div>
                                        </td>
                                        <td data-label="Powód" style="max-width:420px; white-space:pre-wrap">
                                            <?php echo nl2br(htmlspecialchars(($r['message'] ?? '') === 'Brak podanej przyczyny.' ? 'Brak podanej przyczyny' : ($r['message'] ?? ''))); ?>
                                            <?php if (!empty($r['risk_flags'])): ?>
                                                <div class="small text-danger fw-bold mt-2"><?php echo nl2br(htmlspecialchars($r['risk_flags'])); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Status">
                                            <span class="badge bg-<?php echo ($r['status'] ?? '') === 'closed' ? 'dark' : (($r['status'] ?? '') === 'read' ? 'secondary' : 'primary'); ?> bg-opacity-10 text-<?php echo ($r['status'] ?? '') === 'closed' ? 'dark' : (($r['status'] ?? '') === 'read' ? 'secondary' : 'primary'); ?> rounded-pill px-3">
                                                <?php echo ($r['status'] ?? '') === 'closed' ? 'Zamknięta' : (($r['status'] ?? '') === 'read' ? 'Przeczytana' : 'Nowa'); ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small" data-label="Czas"><?php echo date('d.m.Y H:i', strtotime($r['created_at'])); ?></td>
                                        <td class="text-end" data-label="Decyzja">
                                            <div class="d-flex justify-content-end gap-2 flex-wrap admin-requests-actions">
                                                <?php if (($r['status'] ?? '') !== 'closed'): ?>
                                                <form method="POST">
                                                    <?= csrfTokenField('admin_requests'); ?>
                                                    <input type="hidden" name="action" value="resolve_teacher_application">
                                                    <input type="hidden" name="request_id" value="<?= (int)$r['id']; ?>">
                                                    <input type="hidden" name="decision" value="approve">
                                                    <button class="btn btn-success btn-sm rounded-pill px-3">Akceptuj</button>
                                                </form>
                                                <form method="POST">
                                                    <?= csrfTokenField('admin_requests'); ?>
                                                    <input type="hidden" name="action" value="resolve_teacher_application">
                                                    <input type="hidden" name="request_id" value="<?= (int)$r['id']; ?>">
                                                    <input type="hidden" name="decision" value="reject">
                                                    <button class="btn btn-outline-danger btn-sm rounded-pill px-3">Odrzuć</button>
                                                </form>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#replyModal<?= (int)$r['id']; ?>">Szczegóły</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="dashboard-panel p-0 overflow-hidden admin-requests-table-panel">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h5 class="fw-bold mb-1"><i class="bi bi-envelope-open text-primary me-2"></i>Wnioski</h5>
                                <div class="text-muted small">Zwykłe zgłoszenia i prośby do administracji.</div>
                            </div>
                            <span class="badge bg-primary rounded-pill px-3"><?php echo count($generalRequests); ?></span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Użytkownik</th>
                                        <th>Typ</th>
                                        <th>Temat</th>
                                        <th>Treść</th>
                                        <th>Status</th>
                                        <th>Wysłano</th>
                                        <th class="text-end">Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($generalRequests as $r): ?>
                                    <?php
                                        $rUser = ['username' => $r['teacher_username'] ?? '', 'first_name' => $r['first_name'] ?? '', 'last_name' => $r['last_name'] ?? ''];
                                        $generalDisplayName = userDisplayName($rUser);
                                        $generalHandle = userHandle($rUser);
                                    ?>
                                    <tr id="request-<?php echo (int)$r['id']; ?>">
                                        <td class="fw-bold" data-label="Użytkownik">
                                            <?= htmlspecialchars($generalDisplayName); ?>
                                            <?php if ($generalHandle): ?><div class="small text-muted"><?= htmlspecialchars($generalHandle); ?></div><?php endif; ?>
                                        </td>
                                        <td data-label="Typ">
                                            <?php $isTeacherApplication = ($r['type'] ?? 'general') === 'teacher_application'; ?>
                                            <span class="badge <?= $isTeacherApplication ? 'bg-warning text-dark' : 'bg-light text-dark border'; ?>">
                                                <?= $isTeacherApplication ? 'Aplikacja nauczyciela' : 'Wniosek'; ?>
                                            </span>
                                        </td>
                                        <td data-label="Temat"><?= htmlspecialchars($r['subject']); ?></td>
                                        <td data-label="Treść" style="max-width:380px; white-space:pre-wrap"><?= nl2br(htmlspecialchars($r['message'])); ?></td>
                                        <td data-label="Status">
                                            <span class="badge bg-<?= $r['status'] === 'closed' ? 'dark' : ($r['status'] === 'replied' ? 'success' : ($r['status'] === 'read' ? 'secondary' : 'primary')); ?> bg-opacity-10 text-<?= $r['status'] === 'closed' ? 'dark' : ($r['status'] === 'replied' ? 'success' : ($r['status'] === 'read' ? 'secondary' : 'primary')); ?> rounded-pill px-3">
                                                <?= $r['status'] === 'closed' ? 'Zamknięty' : ($r['status'] === 'replied' ? 'Odpowiedziano' : ($r['status'] === 'read' ? 'Przeczytano' : 'Nowy')); ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small" data-label="Wysłano"><?= date('d.m.Y H:i', strtotime($r['created_at'])); ?></td>
                                        <td class="text-end" data-label="Akcje">
                                            <div class="d-flex justify-content-end gap-2 flex-wrap admin-requests-actions">
                                                <?php if (!in_array($r['status'] ?? '', ['read', 'replied', 'closed'], true)): ?>
                                                <form method="POST">
                                                    <?= csrfTokenField('admin_requests'); ?>
                                                    <input type="hidden" name="action" value="mark_read">
                                                    <input type="hidden" name="request_id" value="<?= $r['id']; ?>">
                                                    <button class="btn btn-outline-secondary btn-sm rounded-pill px-3">Oznacz jako przeczytane</button>
                                                </form>
                                                <?php endif; ?>

                                                <?php if (($r['status'] ?? '') !== 'closed'): ?>
                                                    <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#replyModal<?= $r['id']; ?>">
                                                        <?= $r['status'] === 'replied' ? 'Edytuj odpowiedź' : 'Odpowiedz'; ?>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($isTeacherApplication && ($r['status'] ?? '') !== 'closed'): ?>
                                                <form method="POST">
                                                    <?= csrfTokenField('admin_requests'); ?>
                                                    <input type="hidden" name="action" value="resolve_teacher_application">
                                                    <input type="hidden" name="request_id" value="<?= $r['id']; ?>">
                                                    <input type="hidden" name="decision" value="approve">
                                                    <button class="btn btn-success btn-sm rounded-pill px-3">Akceptuj</button>
                                                </form>
                                                <form method="POST">
                                                    <?= csrfTokenField('admin_requests'); ?>
                                                    <input type="hidden" name="action" value="resolve_teacher_application">
                                                    <input type="hidden" name="request_id" value="<?= $r['id']; ?>">
                                                    <input type="hidden" name="decision" value="reject">
                                                    <button class="btn btn-outline-danger btn-sm rounded-pill px-3">Odrzuć</button>
                                                </form>
                                                <?php endif; ?>
                                                <form method="POST" onsubmit="return appConfirmSubmit(this, 'Usunąć ten wniosek?')">
                                                    <?= csrfTokenField('admin_requests'); ?>
                                                    <input type="hidden" name="action" value="delete_request">
                                                    <input type="hidden" name="request_id" value="<?= $r['id']; ?>">
                                                    <button class="btn btn-outline-danger btn-sm rounded-pill px-3" aria-label="Usuń wniosek"><i class="bi bi-trash3"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </main>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <!-- Modals container - moved to root body level to avoid stacking context issues -->
    <?php foreach ($requests as $r): ?>
    <?php $modalUser = ['username' => $r['teacher_username'] ?? '', 'first_name' => $r['first_name'] ?? '', 'last_name' => $r['last_name'] ?? '']; ?>
    <div class="modal fade" id="replyModal<?= $r['id']; ?>" tabindex="-1" aria-labelledby="replyModalLabel<?= $r['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1.25rem;">
                <form method="POST">
                    <?= csrfTokenField('admin_requests'); ?>
                    <div class="modal-header border-0 pb-0 pt-4 px-4">
                        <h5 class="modal-title fw-800" id="replyModalLabel<?= $r['id']; ?>">Odpowiedz na wniosek</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-4 p-4" style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 1.25rem;">
                            <div class="row g-4">
                                <div class="col-sm-6">
                                    <label class="text-muted small fw-bold text-uppercase d-block mb-1" style="letter-spacing: 0.05em;">Użytkownik</label>
                                    <span class="fw-bold fs-5" style="color: var(--text-main);"><?= htmlspecialchars(userDisplayName($modalUser)); ?></span>
                                    <?php if (userHandle($modalUser)): ?><div class="small text-muted"><?= htmlspecialchars(userHandle($modalUser)); ?></div><?php endif; ?>
                                    <?php if (($r['trust_status'] ?? 'trusted') === 'untrusted'): ?>
                                        <div class="mt-2">
                                            <span class="badge bg-danger rounded-pill">untrusted</span>
                                            <span class="badge bg-warning text-dark rounded-pill">possible fraud / duplicate identity</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small fw-bold text-uppercase d-block mb-1" style="letter-spacing: 0.05em;">Temat wniosku</label>
                                    <span class="fw-bold fs-5" style="color: var(--text-main);"><?= htmlspecialchars($r['subject']); ?></span>
                                </div>
                                <div class="col-12">
                                    <label class="text-muted small fw-bold text-uppercase d-block mb-2" style="letter-spacing: 0.05em;">Treść zgłoszenia</label>
                                    <div class="p-3 border-start border-4 border-primary shadow-sm" style="background: var(--panel-bg); border-radius: 0 0.75rem 0.75rem 0; color: var(--text-main);">
                                        <?= nl2br(htmlspecialchars($r['message'])); ?>
                                    </div>
                                    <?php if (!empty($r['risk_flags'])): ?>
                                        <div class="small text-danger fw-bold mt-2"><?php echo nl2br(htmlspecialchars($r['risk_flags'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php $replyHistory = getAdminRequestReplies($pdo, (int)$r['id']); ?>
                        <?php if (!empty($replyHistory)): ?>
                        <div class="mb-4">
                            <label class="form-label fw-bold mb-2">Historia odpowiedzi</label>
                            <div class="vstack gap-2">
                                <?php foreach ($replyHistory as $reply): ?>
                                    <div class="p-3 rounded-4 border" style="background: var(--panel-bg);">
                                        <div class="small text-muted mb-1">
                                            <?php echo htmlspecialchars($reply['admin_username'] ?? 'Administrator'); ?> • <?php echo htmlspecialchars($reply['created_at']); ?>
                                        </div>
                                        <div><?php echo nl2br(htmlspecialchars($reply['reply_text'])); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="mb-0">
                            <label class="form-label fw-bold mb-2">Nowa odpowiedź</label>
                            <textarea name="reply_text" class="form-control rounded-4 shadow-sm" rows="6" placeholder="Dopisz kolejną odpowiedź dla nauczyciela..." style="background: var(--panel-bg); color: var(--text-main); border-color: var(--border-color); resize:none;" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 pb-4 px-4">
                        <input type="hidden" name="action" value="reply_request">
                        <input type="hidden" name="request_id" value="<?= $r['id']; ?>">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Wyślij odpowiedź</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
