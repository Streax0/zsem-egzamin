<?php
$current_page = basename($_SERVER['PHP_SELF'], ".php");
// Determine base path by looking for a root-level file
$base_url = file_exists('config/db.php') ? '' : '../';
$isGuestSidebar = function_exists('isGuestMode') && isGuestMode();
$isFullyLoggedOut = !isset($_SESSION['user_id']) && !$isGuestSidebar;
?>
<a class="skip-link" href="#main-content">Przejdź do treści</a>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const main = document.querySelector('main');
    if (main && !main.id) {
        main.id = 'main-content';
        main.setAttribute('tabindex', '-1');
    }
    const menu = document.querySelector('.sidebar-menu');
    if (menu) {
        const key = 'zsem.sidebar.scrollTop';
        const saved = sessionStorage.getItem(key);
        if (saved !== null) {
            menu.scrollTop = Number(saved) || 0;
        }
        let raf = 0;
        menu.addEventListener('scroll', () => {
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(() => sessionStorage.setItem(key, String(menu.scrollTop)));
        }, { passive: true });
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', () => sessionStorage.setItem(key, String(menu.scrollTop)));
        });
    }
});
</script>
<aside class="sidebar">
    <div class="sidebar-brand d-flex justify-content-between align-items-center">
        <a href="<?php echo $base_url; ?>index.php" class="d-flex align-items-center gap-2 text-decoration-none zsem-brand" aria-label="ZSEM Tech">
            <span class="brand-text">zsemtech</span>
        </a>
        <button class="btn btn-link text-white p-0 d-md-none" id="sidebarClose" type="button" aria-label="Zamknij menu boczne">
            <i class="bi bi-x-lg fs-4" aria-hidden="true"></i>
        </button>
    </div>
    
    <nav class="sidebar-menu" role="navigation" aria-label="Menu główne">
        <a href="<?php echo $base_url; ?>index.php" class="sidebar-item <?php echo $current_page == 'index' ? 'active' : ''; ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?php echo $base_url; ?>test.php?setup=1&new=1" class="sidebar-item <?php echo $current_page == 'test' ? 'active' : ''; ?>">
            <i class="bi bi-journal-text"></i>
            <span>Testy</span>
        </a>
        <?php if ($isFullyLoggedOut): ?>
        <div class="sidebar-divider my-3 opacity-25 border-top border-white mx-3"></div>
        <a href="<?php echo $base_url; ?>login.php" class="sidebar-item">
            <i class="bi bi-box-arrow-in-right"></i>
            <span>Zaloguj</span>
        </a>
        <a href="<?php echo $base_url; ?>register.php" class="sidebar-item">
            <i class="bi bi-person-plus"></i>
            <span>Załóż konto</span>
        </a>
        <?php elseif ($isGuestSidebar): ?>
        <a href="<?php echo $base_url; ?>categories.php" class="sidebar-item <?php echo $current_page == 'categories' ? 'active' : ''; ?>">
            <i class="bi bi-tags"></i>
            <span>Kategorie</span>
        </a>
        <a href="<?php echo $base_url; ?>practice.php" class="sidebar-item <?php echo $current_page == 'practice' ? 'active' : ''; ?>">
            <i class="bi bi-tools"></i>
            <span>Praktyka</span>
        </a>
        <a href="<?php echo $base_url; ?>lessons.php" class="sidebar-item <?php echo $current_page == 'lessons' ? 'active' : ''; ?>">
            <i class="bi bi-easel2"></i>
            <span>Lekcje</span>
        </a>
        <a href="<?php echo $base_url; ?>ranking.php" class="sidebar-item <?php echo $current_page == 'ranking' ? 'active' : ''; ?>">
            <i class="bi bi-trophy"></i>
            <span>Ranking</span>
        </a>
        <a href="<?php echo $base_url; ?>dictionary.php" class="sidebar-item <?php echo $current_page == 'dictionary' ? 'active' : ''; ?>">
            <i class="bi bi-book"></i>
            <span>Słownik pojęć</span>
        </a>
        <a href="<?php echo $base_url; ?>flashcards.php" class="sidebar-item <?php echo $current_page == 'flashcards' ? 'active' : ''; ?>">
            <i class="bi bi-card-text"></i>
            <span>Fiszki</span>
        </a>
        <a href="<?php echo $base_url; ?>sandbox.php" class="sidebar-item <?php echo $current_page == 'sandbox' ? 'active' : ''; ?>">
            <i class="bi bi-cpu"></i>
            <span>Sandbox</span>
        </a>
        <a href="<?php echo $base_url; ?>progress.php" class="sidebar-item <?php echo $current_page == 'progress' ? 'active' : ''; ?>">
            <i class="bi bi-bar-chart-line"></i>
            <span>Statystyki</span>
        </a>
        <a href="<?php echo $base_url; ?>exam/join.php" class="sidebar-item <?php echo strpos($_SERVER['PHP_SELF'], 'exam/join') !== false ? 'active' : ''; ?>">
            <i class="bi bi-qr-code-scan text-info"></i>
            <span>Sprawdzian</span>
        </a>
        <div class="sidebar-divider my-3 opacity-25 border-top border-white mx-3"></div>
        <a href="<?php echo $base_url; ?>login.php" class="sidebar-item">
            <i class="bi bi-box-arrow-in-right"></i>
            <span>Zaloguj</span>
        </a>
        <a href="<?php echo $base_url; ?>register.php" class="sidebar-item">
            <i class="bi bi-person-plus"></i>
            <span>Załóż konto</span>
        </a>
        <?php else: ?>
        <a href="<?php echo $base_url; ?>categories.php" class="sidebar-item <?php echo $current_page == 'categories' ? 'active' : ''; ?>">
            <i class="bi bi-tags"></i>
            <span>Kategorie</span>
        </a>
        <a href="<?php echo $base_url; ?>practice.php" class="sidebar-item <?php echo $current_page == 'practice' ? 'active' : ''; ?>">
            <i class="bi bi-tools"></i>
            <span>Praktyka</span>
        </a>
        <a href="<?php echo $base_url; ?>lessons.php" class="sidebar-item <?php echo $current_page == 'lessons' ? 'active' : ''; ?>">
            <i class="bi bi-easel2"></i>
            <span>Lekcje</span>
        </a>
        <a href="<?php echo $base_url; ?>ranking.php" class="sidebar-item <?php echo $current_page == 'ranking' ? 'active' : ''; ?>">
            <i class="bi bi-trophy"></i>
            <span>Ranking</span>
        </a>
        <a href="<?php echo $base_url; ?>dictionary.php" class="sidebar-item <?php echo $current_page == 'dictionary' ? 'active' : ''; ?>">
            <i class="bi bi-book"></i>
            <span>Słownik pojęć</span>
        </a>
        <a href="<?php echo $base_url; ?>flashcards.php" class="sidebar-item <?php echo $current_page == 'flashcards' ? 'active' : ''; ?>">
            <i class="bi bi-card-text"></i>
            <span>Fiszki</span>
        </a>
        <a href="<?php echo $base_url; ?>sandbox.php" class="sidebar-item <?php echo $current_page == 'sandbox' ? 'active' : ''; ?>">
            <i class="bi bi-cpu"></i>
            <span>Sandbox</span>
        </a>
        <a href="<?php echo $base_url; ?>social.php" class="sidebar-item <?php echo $current_page == 'social' ? 'active' : ''; ?>">
            <i class="bi bi-people"></i>
            <span>Społeczność</span>
        </a>
        <a href="<?php echo $base_url; ?>progress.php" class="sidebar-item <?php echo $current_page == 'progress' ? 'active' : ''; ?>">
            <i class="bi bi-bar-chart-line"></i>
            <span>Statystyki</span>
        </a>
        <a href="<?php echo $base_url; ?>goals.php" class="sidebar-item <?php echo $current_page == 'goals' ? 'active' : ''; ?>">
            <i class="bi bi-lightning-charge"></i>
            <span>Misje</span>
        </a>
        <a href="<?php echo $base_url; ?>history.php" class="sidebar-item <?php echo $current_page == 'history' ? 'active' : ''; ?>">
            <i class="bi bi-clock-history"></i>
            <span>Historia</span>
        </a>
        <a href="<?php echo $base_url; ?>exam/join.php" class="sidebar-item <?php echo strpos($_SERVER['PHP_SELF'], 'exam/join') !== false ? 'active' : ''; ?>">
            <i class="bi bi-qr-code-scan text-info"></i>
            <span>Sprawdzian</span>
        </a>
        <a href="<?php echo $base_url; ?>settings.php" class="sidebar-item <?php echo $current_page == 'settings' ? 'active' : ''; ?>">
            <i class="bi bi-gear"></i>
            <span>Ustawienia</span>
        </a>

        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['teacher', 'admin', 'dyrektor'])): ?>
        <div class="sidebar-divider my-3 opacity-25 border-top border-white mx-3"></div>
        <div class="px-4 mb-2 small text-uppercase fw-bold opacity-50 text-white" style="font-size: 0.65rem;">Nauczyciel</div>
        <a href="<?php echo $base_url; ?>teacher/index.php" class="sidebar-item <?php echo strpos($_SERVER['PHP_SELF'], 'teacher/index') !== false ? 'active' : ''; ?>">
            <i class="bi bi-clipboard2-pulse-fill text-info"></i>
            <span>Panel nauczyciela</span>
        </a>
        <a href="<?php echo $base_url; ?>teacher/custom_exams.php" class="sidebar-item <?php echo strpos($_SERVER['PHP_SELF'], 'custom_exam') !== false ? 'active' : ''; ?>">
            <i class="bi bi-pencil-square text-warning"></i>
            <span>Moje sprawdziany</span>
        </a>
        <a href="<?php echo $base_url; ?>teacher/pdf_generator.php" class="sidebar-item <?php echo strpos($_SERVER['PHP_SELF'], 'teacher/pdf_generator') !== false ? 'active' : ''; ?>">
            <i class="bi bi-file-earmark-pdf text-danger"></i>
            <span>Generator sprawdzianów</span>
        </a>
        <a href="<?php echo $base_url; ?>teacher/requests.php" class="sidebar-item <?php echo strpos($_SERVER['PHP_SELF'], 'teacher/requests') !== false ? 'active' : ''; ?>">
            <i class="bi bi-send-fill text-success"></i>
            <span>Wnioski do admina</span>
        </a>
        <?php endif; ?>

        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)): ?>
        <div class="sidebar-divider my-3 opacity-25 border-top border-white mx-3"></div>
        <div class="px-4 mb-2 small text-uppercase fw-bold opacity-50 text-white" style="font-size: 0.65rem;">Administracja</div>
        <a href="<?php echo $base_url; ?>admin.php" class="sidebar-item <?php echo $current_page == 'admin' ? 'active' : ''; ?>">
            <i class="bi bi-shield-lock-fill text-danger"></i>
            <span>Panel Admin</span>
        </a>
        <a href="<?php echo $base_url; ?>manage_questions.php" class="sidebar-item <?php echo $current_page == 'manage_questions' ? 'active' : ''; ?>">
            <i class="bi bi-database-fill-gear text-warning"></i>
            <span>Baza Pytań</span>
        </a>
        <a href="<?php echo $base_url; ?>admin_requests.php" class="sidebar-item <?php echo $current_page == 'admin_requests' ? 'active' : ''; ?>">
            <i class="bi bi-envelope-open text-primary"></i>
            <span>Wnioski i role</span>
        </a>
        <?php endif; ?>

        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['wujek_luki', 'admin'], true)): ?>
        <div class="sidebar-divider my-3 opacity-25 border-top border-white mx-3"></div>
        <div class="px-4 mb-2 small text-uppercase fw-bold opacity-50 text-white" style="font-size: 0.65rem;">Specjalne</div>
        <a href="<?php echo $base_url; ?>luki_panel.php" class="sidebar-item <?php echo $current_page == 'luki_panel' ? 'active' : ''; ?>">
            <i class="bi bi-star-fill text-warning"></i>
            <span>Panel Lukiego</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <?php if (!$isFullyLoggedOut): ?>
        <form action="<?php echo $base_url; ?>actions/logout.php" method="POST" class="m-0">
            <?php echo csrfTokenField('logout'); ?>
            <button type="submit" class="sidebar-item text-danger w-100 border-0 bg-transparent">
                <i class="bi <?php echo $isGuestSidebar ? 'bi-door-open' : 'bi-box-arrow-right'; ?>"></i>
                <span><?php echo $isGuestSidebar ? 'Wyjdź' : 'Wyloguj'; ?></span>
            </button>
        </form>
        <?php endif; ?>
    </div>
</aside>
