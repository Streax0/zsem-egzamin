<?php
/**
 * Shared navigation bar component
 * Include on all authenticated pages for consistent UI
 * 
 * Expected variables before including:
 *   $pdo (from config/db.php)
 *   $_SESSION['username'], $_SESSION['user_id'], $_SESSION['role']
 * 
 * Optional: $activePage = 'index' | 'test' | 'profile' | 'progress' | 'admin'
 */
$activePage = $activePage ?? '';
$navIsGuest = function_exists('isGuestMode') && isGuestMode();
$navUsername = htmlspecialchars($_SESSION['username'] ?? 'Użytkownik');
$navRole = $_SESSION['role'] ?? 'user';
$navIsAdmin = function_exists('roleHasAdminAccess') ? roleHasAdminAccess($navRole) : in_array($navRole, ['admin', 'dyrektor'], true);
?>
<nav class="navbar navbar-expand-lg navbar-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold fs-5 d-flex align-items-center gap-2" href="index.php">
            <div class="bg-opacity-20 bg-white rounded-circle p-2" style="background-color: rgba(255,255,255,0.2);">
                <i class="bi bi-journal-check text-white"></i>
            </div>
            System Testów
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Przełącz nawigację">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if (isset($isTestPage) && $isTestPage && isset($mode) && $mode === 'exam'): ?>
                <li class="nav-item me-lg-3 mb-2 mb-lg-0">
                    <form method="POST" onsubmit="return typeof confirmFinish === 'function' ? confirmFinish(this) : true;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="finish_early">
                        <button type="submit" class="btn btn-warning btn-sm fw-bold">
                            <i class="bi bi-flag-fill me-1"></i>Zakończ egzamin
                        </button>
                    </form>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'index' ? 'active' : '' ?>" href="index.php">
                        <i class="bi bi-house me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'progress' ? 'active' : '' ?>" href="progress.php">
                        <i class="bi bi-graph-up me-1"></i>Postęp
                    </a>
                </li>
                <?php if ($navIsAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'admin' ? 'active' : '' ?>" href="admin.php">
                        <i class="bi bi-shield-lock me-1"></i>Admin
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-circle">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <span class="d-none d-lg-inline"><?= $navUsername ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="profile.php">
                                <i class="bi bi-person-circle me-2"></i>Mój profil
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="progress.php">
                                <i class="bi bi-graph-up me-2"></i>Mój postęp
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="actions/logout.php" method="POST" class="m-0">
                                <?= csrfTokenField('logout'); ?>
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi <?= $navIsGuest ? 'bi-door-open' : 'bi-box-arrow-right'; ?> me-2"></i><?= $navIsGuest ? 'Wyjdź' : 'Wyloguj się'; ?>
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
