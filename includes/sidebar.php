<?php
$php_self = $_SERVER['PHP_SELF'];
$base_url = file_exists('config/db.php') ? '' : '../';
$isGuestSidebar = function_exists('isGuestMode') && isGuestMode();
$isFullyLoggedOut = !isset($_SESSION['user_id']) && !$isGuestSidebar;

if (!function_exists('isActive')) {
    function isActive($path, $php_self) {
        return strpos($php_self, $path) !== false ? 'active' : '';
    }
}
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
            <i class="bi bi-x-lg fs-5" aria-hidden="true"></i>
        </button>
    </div>
    
    <nav class="sidebar-menu" role="navigation" aria-label="Menu główne">
        <div class="sidebar-group-title">Główne</div>
        <a href="<?php echo $base_url; ?>index.php" class="sidebar-item <?php echo (strpos($php_self, '/index.php') !== false && strpos($php_self, '/admin/') === false && strpos($php_self, '/teacher/') === false && strpos($php_self, '/sandbox/') === false) ? 'active' : ''; ?>">
            <i class="bi bi-grid-1x2"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?php echo $base_url; ?>courses.php" class="sidebar-item <?php echo isActive('/courses.php', $php_self) || isActive('/course_view.php', $php_self) || isActive('/course_learn.php', $php_self); ?>">
            <i class="bi bi-mortarboard"></i>
            <span>Kursy</span>
        </a>
        <a href="<?php echo $base_url; ?>test.php?setup=1&new=1" class="sidebar-item <?php echo isActive('/test.php', $php_self); ?>">
            <i class="bi bi-journal-text"></i>
            <span>Testy</span>
        </a>

        <?php if ($isFullyLoggedOut): ?>
            <div class="sidebar-group-title mt-3">Konto</div>
            <a href="<?php echo $base_url; ?>login.php" class="sidebar-item">
                <i class="bi bi-box-arrow-in-right"></i>
                <span>Zaloguj</span>
            </a>
            <a href="<?php echo $base_url; ?>register.php" class="sidebar-item">
                <i class="bi bi-person-plus"></i>
                <span>Załóż konto</span>
            </a>
        <?php else: ?>
            <div class="sidebar-group-title mt-3">Nauka</div>
            <a href="<?php echo $base_url; ?>categories.php" class="sidebar-item <?php echo isActive('/categories.php', $php_self); ?>" <?php echo $isGuestSidebar ? 'data-guest-restricted="1" data-guest-feature="Kategorie pytań"' : ''; ?>>
                <i class="bi bi-tags"></i>
                <span>Kategorie</span>
            </a>
            <a href="<?php echo $base_url; ?>practice.php" class="sidebar-item <?php echo isActive('/practice.php', $php_self); ?>">
                <i class="bi bi-tools"></i>
                <span>Praktyka</span>
            </a>
            <a href="<?php echo $base_url; ?>lessons.php" class="sidebar-item <?php echo isActive('/lessons.php', $php_self); ?>">
                <i class="bi bi-easel2"></i>
                <span>Lekcje</span>
            </a>
            <a href="<?php echo $base_url; ?>sandbox/index.php" class="sidebar-item <?php echo isActive('/sandbox/index.php', $php_self); ?>">
                <i class="bi bi-terminal"></i>
                <span>Sandbox</span>
            </a>

            <div class="sidebar-group-title mt-3">Społeczność</div>
            <a href="<?php echo $base_url; ?>ranking.php" class="sidebar-item <?php echo isActive('/ranking.php', $php_self); ?>" <?php echo $isGuestSidebar ? 'data-guest-restricted="1" data-guest-feature="Ranking uczniów"' : ''; ?>>
                <i class="bi bi-trophy"></i>
                <span>Ranking</span>
            </a>
            <a href="<?php echo $base_url; ?>dictionary.php" class="sidebar-item <?php echo isActive('/dictionary.php', $php_self); ?>">
                <i class="bi bi-book"></i>
                <span>Słownik pojęć</span>
            </a>
            <a href="<?php echo $base_url; ?>flashcards.php" class="sidebar-item <?php echo isActive('/flashcards.php', $php_self); ?>">
                <i class="bi bi-card-text"></i>
                <span>Fiszki</span>
            </a>
            <?php if (!$isGuestSidebar): ?>
                <a href="<?php echo $base_url; ?>user/social.php" class="sidebar-item <?php echo isActive('/user/social.php', $php_self); ?>">
                    <i class="bi bi-people"></i>
                    <span>Społeczność</span>
                </a>
                <a href="<?php echo $base_url; ?>user/progress.php" class="sidebar-item <?php echo isActive('/user/progress.php', $php_self); ?>">
                    <i class="bi bi-bar-chart-line"></i>
                    <span>Statystyki</span>
                </a>
                <a href="<?php echo $base_url; ?>user/goals.php" class="sidebar-item <?php echo isActive('/user/goals.php', $php_self); ?>">
                    <i class="bi bi-lightning-charge"></i>
                    <span>Misje</span>
                </a>
            <?php endif; ?>

            <a href="<?php echo $base_url; ?>exam/join.php" class="sidebar-item <?php echo strpos($_SERVER['PHP_SELF'], 'exam/join') !== false ? 'active' : ''; ?>">
                <i class="bi bi-qr-code-scan"></i>
                <span>Sprawdzian</span>
            </a>

            <?php if (!$isGuestSidebar): ?>
                <a href="<?php echo $base_url; ?>user/settings.php" class="sidebar-item <?php echo isActive('/user/settings.php', $php_self); ?>">
                    <i class="bi bi-gear"></i>
                    <span>Ustawienia</span>
                </a>
            <?php endif; ?>

            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['teacher', 'admin', 'dyrektor'])): ?>
                <div class="sidebar-group-title mt-3">Strefa Nauczyciela</div>
                <a href="<?php echo $base_url; ?>teacher/index.php" class="sidebar-item <?php echo strpos($_SERVER['PHP_SELF'], 'teacher/index') !== false ? 'active' : ''; ?>">
                    <i class="bi bi-clipboard2-pulse"></i>
                    <span>Panel nauczyciela</span>
                </a>
                <a href="<?php echo $base_url; ?>teacher/custom_exams.php" class="sidebar-item <?php echo strpos($_SERVER['PHP_SELF'], 'custom_exam') !== false ? 'active' : ''; ?>">
                    <i class="bi bi-pencil-square"></i>
                    <span>Moje sprawdziany</span>
                </a>
                <a href="<?php echo $base_url; ?>teacher/pdf_generator.php" class="sidebar-item <?php echo strpos($_SERVER['PHP_SELF'], 'teacher/pdf_generator') !== false ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-pdf"></i>
                    <span>Generator sprawdzianów</span>
                </a>
                <a href="<?php echo $base_url; ?>teacher/requests.php" class="sidebar-item <?php echo strpos($_SERVER['PHP_SELF'], 'teacher/requests') !== false ? 'active' : ''; ?>">
                    <i class="bi bi-send"></i>
                    <span>Wnioski do admina</span>
                </a>
            <?php endif; ?>

            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)): ?>
                <div class="sidebar-group-title mt-3">Administracja</div>
                <a href="<?php echo $base_url; ?>admin/index.php" class="sidebar-item <?php echo isActive('/admin/index.php', $php_self); ?>">
                    <i class="bi bi-shield-lock"></i>
                    <span>Panel Admin</span>
                </a>
                <a href="<?php echo $base_url; ?>admin/system_health.php" class="sidebar-item <?php echo isActive('/admin/system_health.php', $php_self); ?>">
                    <i class="bi bi-heart-pulse"></i>
                    <span>Stan Systemu</span>
                </a>
                <a href="<?php echo $base_url; ?>admin/engine.php" class="sidebar-item <?php echo isActive('/admin/engine.php', $php_self); ?>"><i class="bi bi-cpu"></i> <span>Silnik i Security</span></a>
                <a href="<?php echo $base_url; ?>admin/manage_questions.php" class="sidebar-item <?php echo isActive('/admin/manage_questions.php', $php_self); ?>">
                    <i class="bi bi-database-gear"></i>
                    <span>Baza Pytań</span>
                </a>
                <a href="<?php echo $base_url; ?>admin/manage_courses.php" class="sidebar-item <?php echo isActive('/admin/manage_courses.php', $php_self) || isActive('/admin/course_builder.php', $php_self); ?>">
                    <i class="bi bi-kanban"></i>
                    <span>Zarządzanie kursami</span>
                </a>
                <a href="<?php echo $base_url; ?>admin/requests.php" class="sidebar-item <?php echo isActive('/admin/requests.php', $php_self); ?>">
                    <i class="bi bi-envelope-open"></i>
                    <span>Wnioski i role</span>
                </a>
            <?php endif; ?>

            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['wujek_luki', 'admin'], true)): ?>
                <div class="sidebar-group-title mt-3">Specjalne</div>
                <a href="<?php echo $base_url; ?>sandbox/luki_panel.php" class="sidebar-item <?php echo isActive('/sandbox/luki_panel.php', $php_self); ?>">
                    <i class="bi bi-star"></i>
                    <span>Panel Lukiego</span>
                </a>
            <?php endif; ?>

            <?php if ($isGuestSidebar): ?>
                <div class="sidebar-group-title mt-3">Konto</div>
                <a href="<?php echo $base_url; ?>login.php" class="sidebar-item">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span>Zaloguj</span>
                </a>
                <a href="<?php echo $base_url; ?>register.php" class="sidebar-item">
                    <i class="bi bi-person-plus"></i>
                    <span>Załóż konto</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <?php if (!$isFullyLoggedOut): ?>
            <form action="<?php echo $base_url; ?>actions/logout.php" method="POST" class="m-0">
                <?php echo csrfTokenField('logout'); ?>
                <button type="submit" class="sidebar-logout-btn w-100">
                    <i class="bi <?php echo $isGuestSidebar ? 'bi-door-open' : 'bi-box-arrow-right'; ?>"></i>
                    <span><?php echo $isGuestSidebar ? 'Wyjdź' : 'Wyloguj'; ?></span>
                </button>
            </form>
        <?php endif; ?>
    </div>
</aside>
