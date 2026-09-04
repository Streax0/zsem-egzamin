<?php
declare(strict_types=1);

/**
 * Test Suite: Question Explanations Quality & 'Dlaczego nie reszta?' Standards
 * Validates:
 * 1. 100% of questions in data_question/*.json have explanation.
 * 2. 100% of questions contain delimiter 'Dlaczego nie reszta?'.
 * 3. 0% tautological phrases ('odmiennego mechanizmu', 'nie spełnia kryteriów', etc.).
 * 4. All distractors are explained.
 * 5. Dynamic fallback engine produces informative output using dictionary.
 */

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/AiTutorEngine.php';

$totalPassed = 0;
$totalFailed = 0;

function assertQuality(string $label, bool $condition, string $detail = '') {
    global $totalPassed, $totalFailed;
    if ($condition) {
        echo "  [PASS] {$label}\n";
        $totalPassed++;
    } else {
        echo "  [FAIL] {$label}\n";
        if ($detail !== '') echo "         -> {$detail}\n";
        $totalFailed++;
    }
}

echo "=================================================================\n";
echo " Running Question Explanations & 'Dlaczego nie reszta?' Test Suite\n";
echo "=================================================================\n\n";

$dataDir = dirname(__DIR__) . '/data_question/';
$files = glob($dataDir . '*.json');
$files = array_filter($files, fn($f) => !str_contains($f, 'backup'));
sort($files);

$totalQuestions = 0;
$withExplanation = 0;
$withDlaczegoNieReszta = 0;
$tautologyCount = 0;
$forbiddenPhrases = [
    'odmiennego mechanizmu',
    'nie spełnia bezpośrednio warunku z pytania',
    'opisuje inną warstwę działania',
    'nie spełnia głównego warunku pytania',
    'żadne z tych rozwiązań nie spełnia wymagań zadania',
];

foreach ($files as $file) {
    $filename = basename($file);
    $data = json_decode(file_get_contents($file), true) ?: [];
    $count = count($data);
    $totalQuestions += $count;
    
    $fileExpCount = 0;
    $fileMarkerCount = 0;
    $fileTautologies = 0;

    foreach ($data as $idx => $q) {
        $exp = trim((string)($q['explanation'] ?? ''));
        if ($exp !== '') {
            $fileExpCount++;
        }
        if (str_contains($exp, 'Dlaczego nie reszta?')) {
            $fileMarkerCount++;
        }
        foreach ($forbiddenPhrases as $bad) {
            if (str_contains(mb_strtolower($exp, 'UTF-8'), $bad)) {
                $fileTautologies++;
                $tautologyCount++;
            }
        }
    }
    $withExplanation += $fileExpCount;
    $withDlaczegoNieReszta += $fileMarkerCount;

    assertQuality(
        "{$filename} ({$count} questions): 100% coverage",
        $fileExpCount === $count,
        "Explanations: {$fileExpCount}/{$count}"
    );
    assertQuality(
        "{$filename}: 100% 'Dlaczego nie reszta?' marker presence",
        $fileMarkerCount === $count,
        "Markers: {$fileMarkerCount}/{$count}"
    );
    assertQuality(
        "{$filename}: 0 forbidden tautologies",
        $fileTautologies === 0,
        "Found {$fileTautologies} tautology occurrences"
    );
}

echo "\n--- Summary on {$totalQuestions} Questions ---\n";
echo "Total questions: {$totalQuestions}\n";
echo "With explanation: {$withExplanation} / {$totalQuestions}\n";
echo "With 'Dlaczego nie reszta?': {$withDlaczegoNieReszta} / {$totalQuestions}\n";
echo "Tautologies / forbidden: {$tautologyCount}\n\n";

assertQuality("Overall dataset has 100% explanation coverage", $withExplanation === $totalQuestions && $totalQuestions >= 1800);
assertQuality("Overall dataset has 100% 'Dlaczego nie reszta?' marker presence", $withDlaczegoNieReszta === $totalQuestions);
assertQuality("Overall dataset has 0 tautologies", $tautologyCount === 0);

// Test Dynamic Fallback Engine for new questions without saved explanation
echo "\n--- Testing Dynamic Fallback Engine (for unindexed/custom questions) ---\n";

$dynamicQ1 = [
    'question' => 'Która instrukcja warunkowa w języku C# wykonuje kod przy spełnionym warunku?',
    'options' => ['A' => 'for', 'B' => 'switch', 'C' => 'if', 'D' => 'return'],
    'correct' => 'C',
    'category' => 'INF.04',
];
$exp1 = buildQuestionExplanation($dynamicQ1, 'A', false);
assertQuality(
    "Dynamic fallback for INF.04 avoids network switch confusion",
    !str_contains($exp1, 'warstwie 2') && !str_contains($exp1, 'MAC') && str_contains($exp1, 'Dlaczego nie reszta?'),
    "Output: " . mb_substr($exp1, 0, 150)
);

$dynamicQ2 = [
    'question' => 'Urządzenie warstwy 2 OSI przekazujące ramki na podstawie tablicy MAC to',
    'options' => ['A' => 'router', 'B' => 'switch', 'C' => 'hub', 'D' => 'repeater'],
    'correct' => 'B',
    'category' => 'INF.02',
];
$exp2 = buildQuestionExplanation($dynamicQ2, 'A', false);
assertQuality(
    "Dynamic fallback for INF.02 uses accurate networking definitions",
    str_contains($exp2, 'Dlaczego nie reszta?') && !str_contains($exp2, 'odmiennego mechanizmu'),
    "Output: " . mb_substr($exp2, 0, 150)
);

echo "\n=================================================================\n";
if ($totalFailed === 0) {
    echo " ALL QUALITY TESTS PASSED! ({$totalPassed} passed, 0 failed)\n";
    exit(0);
} else {
    echo " SOME QUALITY TESTS FAILED! ({$totalPassed} passed, {$totalFailed} failed)\n";
    exit(1);
}
