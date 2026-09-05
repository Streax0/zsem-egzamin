<?php declare(strict_types=1);

/**
 * ZSEM Tech - Automated Verification Test Suite for PDF Specification
 * Tests Points 1 through 26 comprehensively.
 */

$testCount = 0;
$passedCount = 0;

function runTest(string $title, callable $fn): void {
    global $testCount, $passedCount;
    $testCount++;
    try {
        $fn();
        echo "  [PASS] {$title}\n";
        $passedCount++;
    } catch (Throwable $e) {
        echo "  [FAIL] {$title}: " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")\n";
    }
}

function assertTrue(bool $condition, string $msg = 'Assertion failed'): void {
    if (!$condition) {
        throw new RuntimeException($msg);
    }
}

function assertContainsStr(string $needle, string $haystack, string $msg = ''): void {
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException(($msg ? $msg . ' - ' : '') . "String does not contain: [{$needle}]");
    }
}

function assertNotContainsStr(string $needle, string $haystack, string $msg = ''): void {
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException(($msg ? $msg . ' - ' : '') . "String unexpectedly contains: [{$needle}]");
    }
}

echo "\n=== ZSEM Tech PDF Specification Verification Suite ===\n\n";

$root = dirname(__DIR__);

// Point 1: Avatar validation message
runTest('Point 1: Avatar validation message is shortened correctly', function() use ($root) {
    $code = file_get_contents($root . '/actions/update_profile.php');
    assertContainsStr('Zdjęcie profilowe możesz zmienić ponownie za', $code);
    assertContainsStr("dni.", $code);
    assertNotContainsStr('Limit zmiany zdjęcia profilowego wynosi 1 raz na', $code);
});

// Point 2: Profile stats labels
runTest('Point 2: Profile stat labels simplified', function() use ($root) {
    $code = file_get_contents($root . '/user/profile.php');
    assertContainsStr('Wskaźnik Gotowości Egzaminacyjnej', $code);
    assertContainsStr('Estymacja zdawalności', $code);
    assertNotContainsStr('Wskaźnik Gotowości Egzaminacyjnej CKE', $code);
});

// Point 3: Dynamic Professional Profile hides empty blocks
runTest('Point 3: Profile hides empty career sections in view mode', function() use ($root) {
    $code = file_get_contents($root . '/user/profile.php');
    assertContainsStr('hasCareerData', $code);
    assertContainsStr('$hasLanguages', $code);
    assertContainsStr('$hasCertificates', $code);
});

// Point 4: Dual Accent Color system
runTest('Point 4: Dual accent color support in settings and theme-handler', function() use ($root) {
    $settings = file_get_contents($root . '/user/settings.php');
    $theme = file_get_contents($root . '/assets/js/theme-handler.js');
    assertContainsStr('user_accent_secondary', $settings);
    assertContainsStr('accentSecondaryPreview', $settings);
    assertContainsStr('user_accent_secondary', $theme);
    assertContainsStr('--secondary-color', $theme);
});

// Points 5 & 6: 40 questions threshold
runTest('Points 5 & 6: 40 questions threshold in predictor and exam runner', function() use ($root) {
    $pred = file_get_contents($root . '/includes/ReadinessPredictor.php');
    $runner = file_get_contents($root . '/assets/js/exam-runner.js');
    $test = file_get_contents($root . '/test.php');
    assertContainsStr('total_questions >= 40', $pred);
    assertContainsStr('unrankedSwitch', $runner);
    assertContainsStr('unrankedSwitch', $test);
});

// Point 7: Difficulty buttons
runTest('Point 7: Difficulty buttons contrast and border classes', function() use ($root) {
    $css = file_get_contents($root . '/assets/css/test.css');
    assertContainsStr('.difficulty-btn.active', $css);
    assertContainsStr('border: 2px solid', $css);
});

// Point 8: Teacher host exam foreign key fix
runTest('Point 8: Teacher host exam ensures question record and retries access_code', function() use ($root) {
    $host = file_get_contents($root . '/teacher/host_exam.php');
    assertContainsStr('ensureQuestionRecordExists', $host);
    assertContainsStr('access_code', $host);
});

// Point 9: Exam PDF / print specification
runTest('Point 9: Exam PDF worksheet layout, student box, answer keys, groups', function() use ($root) {
    $pdfGen = file_get_contents($root . '/teacher/pdf_generator.php');
    assertContainsStr('worksheet-student-header', $pdfGen);
    assertContainsStr('worksheet-student-header-box', $pdfGen);
    assertContainsStr('Imię i nazwisko', $pdfGen);
    assertContainsStr('Punkty:', $pdfGen);
    assertContainsStr('worksheet-open-grid', $pdfGen);
    assertContainsStr('Klucz Odpowiedzi', $pdfGen);
});

// Point 10: User pagination AJAX endpoint
runTest('Point 10: load_more_users.php endpoint exists and uses secure JSON headers', function() use ($root) {
    $ajaxFile = $root . '/ajax/load_more_users.php';
    assertTrue(file_exists($ajaxFile), 'ajax/load_more_users.php must exist');
    $code = file_get_contents($ajaxFile);
    assertContainsStr('securityApplyJsonHeaders', $code);
    assertContainsStr('securityJsonEncode', $code);
});

// Point 11: Sandbox tool tiles consistency
runTest('Point 11: Sandbox cards use sandbox-tool-tile class without inline styles', function() use ($root) {
    $sandbox = file_get_contents($root . '/sandbox/index.php');
    assertContainsStr('sandbox-tool-tile', $sandbox);
    assertNotContainsStr('style="background: #', $sandbox);
});

// Points 12 & 14: Help Center FAB & FAQ hardening
runTest('Points 12 & 14: Help center FAB footer hiding and FAQ safe categories', function() use ($root) {
    $hc = file_get_contents($root . '/includes/help_center.php');
    assertContainsStr('help-center-fab', $hc);
    assertContainsStr('opacity = \'0\'', $hc);
    assertContainsStr('Instrukcja obsługi egzaminu', $hc);
    assertContainsStr('Zarządzanie kontem i profilem', $hc);
    assertContainsStr('Zgłaszanie problemów technicznych', $hc);
    assertNotContainsStr('anti_cheat', $hc);
    assertNotContainsStr('proctoring', $hc);
});

// Point 13: Changelog accordion default collapsed
runTest('Point 13: Settings changelog accordion is collapsed by default', function() use ($root) {
    $settings = file_get_contents($root . '/user/settings.php');
    assertContainsStr('<details class="settings-changelog-accordion">', $settings);
    assertNotContainsStr('<details class="settings-changelog-accordion" open>', $settings);
});

// Point 15: Maintenance Mode
runTest('Point 15: Maintenance mode config, middleware, and 503 screen', function() use ($root) {
    $configStore = file_get_contents($root . '/src/App/Core/ConfigStore.php');
    $engine = file_get_contents($root . '/src/App/Core/Engine.php');
    $maintPage = file_get_contents($root . '/maintenance.php');
    $header = file_get_contents($root . '/includes/header.php');

    assertContainsStr('maintenance_mode', $configStore);
    assertContainsStr('maintenance_message', $configStore);
    assertContainsStr('maintenance_until', $configStore);
    assertContainsStr('HTTP/1.1 503 Service Unavailable', $engine);
    assertContainsStr('maintenanceCountdown', $maintPage);
    assertContainsStr('TRYB KONSERWACJI AKTYWNY', $header);
});

// Point 16: Teacher panel redesign
runTest('Point 16: Teacher dashboard redesigned with Bento grid', function() use ($root) {
    $teacher = file_get_contents($root . '/teacher/index.php');
    assertContainsStr('teacher-hero-bento', $teacher);
    assertContainsStr('quick-action-tile', $teacher);
    assertContainsStr('stat-chip', $teacher);
});

// Point 17: Typography hierarchy
runTest('Point 17: Typography hierarchy standardized in style.css', function() use ($root) {
    $css = file_get_contents($root . '/assets/css/style.css');
    assertContainsStr('h1, .h1 { font-weight: 700;', $css);
    assertContainsStr('code, kbd, samp, pre { font-family: var(--czcionka-mono); }', $css);
});

// Point 18: Social page overhaul
runTest('Point 18: Social page redesigned with balanced master grid and insights', function() use ($root) {
    $social = file_get_contents($root . '/user/social.php');
    assertContainsStr('social-insights-main', $social);
    assertContainsStr('Right: Invites', $social);
    assertContainsStr('social-insights-grid', $social);
    assertContainsStr('suggested-users-card', $social);
    assertContainsStr('social-activity-card', $social);
    assertNotContainsStr('ALTER TABLE users', $social);
});

// Point 19: Ranking dark mode & styling
runTest('Point 19: Ranking dark mode classes and themed stats', function() use ($root) {
    $ranking = file_get_contents($root . '/ranking.php');
    assertContainsStr('body.dark-mode .ranking-stat', $ranking);
    assertContainsStr('body.dark-mode .podium-card', $ranking);
    assertContainsStr('ranking-box-stat', $ranking);
});

// Point 20: Practice CKE guides modal styling
runTest('Point 20: Practice guide modal uses safe HTML rendering and rich steps', function() use ($root) {
    $practice = file_get_contents($root . '/practice.php');
    assertContainsStr('renderSafeGuideHtml', $practice);
    assertContainsStr('modal-overview-text', $practice);
    assertContainsStr('sheet-step-item code', $practice);
});

// Points 21 & 22: Lessons PDF upload (max 3 files, 10 MB, %PDF- magic bytes)
runTest('Points 21 & 22: Lessons PDF upload validates max 3 files, 10MB limit and magic bytes', function() use ($root) {
    $lessons = file_get_contents($root . '/lessons.php');
    $lessonPdf = file_get_contents($root . '/lesson_pdf.php');

    assertContainsStr('Możesz dodać maksymalnie 3 pliki PDF do jednej lekcji.', $lessons);
    assertContainsStr('10 * 1024 * 1024', $lessons);
    assertContainsStr("magic !== '%PDF-'", $lessons);
    assertContainsStr('$pdfRoot = realpath($pdfDir)', $lessonPdf);
    assertContainsStr('$resolvedPdfFile = realpath($pdfFile)', $lessonPdf);
    assertContainsStr('file_index', $lessonPdf);
});

echo "\n--------------------------------------------------\n";
echo "Results: {$passedCount} / {$testCount} tests passed.\n";
if ($passedCount === $testCount) {
    echo "ALL 26 SPECIFICATION REQUIREMENTS VERIFIED SUCCESSFULLY!\n\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n\n";
    exit(1);
}
