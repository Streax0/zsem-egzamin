<?php
declare(strict_types=1);

/**
 * CKE Exam Readiness Predictor
 * Evaluates student test history, category performance, and statistical variance
 * to estimate pass probability on official state exams (INF.02, INF.03).
 */

function calculateCkeReadinessIndex(PDO $pdo, int $userId): array {
    if ($userId <= 0) {
        return [
            'overall_score' => 0,
            'pass_probability' => 0,
            'tests_count' => 0,
            'category_scores' => [],
            'readiness_label' => 'Brak danych',
            'weakest_category' => null,
            'recommendation' => 'Rozwiąż co najmniej 1 pełny egzamin próbny (40 pytań), aby wygenerować wskaźnik gotowości.',
        ];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, score_percent, correct_answers, total_questions, time_spent, test_date
            FROM test_results
            WHERE user_id = :uid
              AND total_questions >= 40
              AND COALESCE(exclude_from_ranking, 0) = 0
            ORDER BY id DESC
            LIMIT 15
        ");
        $stmt->execute(['uid' => $userId]);
        $recentTests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $recentTests = [];
    }

    if (empty($recentTests)) {
        return [
            'overall_score' => 0,
            'pass_probability' => 0,
            'tests_count' => 0,
            'category_scores' => [],
            'readiness_label' => 'Brak prób',
            'weakest_category' => null,
            'recommendation' => 'Rozwiąż swój pierwszy test egzaminacyjny, aby aktywować algorytm predykcji CKE.',
        ];
    }

    $scores = array_map(fn($t) => (float)$t['score_percent'], $recentTests);
    $avgScore = array_sum($scores) / count($scores);

    // Calculate standard deviation / variance
    $variance = 0.0;
    foreach ($scores as $s) {
        $variance += pow($s - $avgScore, 2);
    }
    $stdDev = sqrt($variance / count($scores));

    // Pass probability heuristic (CKE passing mark is 50%)
    $passProbability = min(99, max(5, (int)round($avgScore * 0.95 - ($stdDev * 0.25))));
    if ($avgScore >= 75) {
        $passProbability = min(99, $passProbability + 5);
    }

    // Category breakdown
    $categoryScores = [];
    try {
        $testIds = array_column($recentTests, 'id');
        if (!empty($testIds)) {
            $inClause = implode(',', array_map('intval', $testIds));
            $cStmt = $pdo->query("
                SELECT q.category, COUNT(ta.id) AS total_ans, SUM(CASE WHEN ta.is_correct = 1 THEN 1 ELSE 0 END) AS correct_ans
                FROM test_answers ta
                JOIN questions q ON q.id = ta.question_id
                WHERE ta.result_id IN ($inClause)
                GROUP BY q.category
            ");
            if ($cStmt) {
                while ($cRow = $cStmt->fetch(PDO::FETCH_ASSOC)) {
                    $cat = (string)($cRow['category'] ?? 'Ogólne');
                    $tot = (int)$cRow['total_ans'];
                    $cor = (int)$cRow['correct_ans'];
                    $pct = $tot > 0 ? (int)round(($cor / $tot) * 100) : 0;
                    $categoryScores[$cat] = [
                        'name' => $cat,
                        'total' => $tot,
                        'correct' => $cor,
                        'score_percent' => $pct,
                    ];
                }
            }
        }
    } catch (Throwable $e) {}

    // Identify weakest category
    $weakestCat = null;
    $lowestScore = 101;
    foreach ($categoryScores as $catKey => $catData) {
        if ($catData['score_percent'] < $lowestScore) {
            $lowestScore = $catData['score_percent'];
            $weakestCat = $catKey;
        }
    }

    $label = 'Średnia gotowość';
    if ($passProbability >= 85) {
        $label = 'Wysoka gotowość (Pewne zdanie)';
    } elseif ($passProbability >= 65) {
        $label = 'Zadowalająca (Zaliczony próg 50%)';
    } else {
        $label = 'Wymaga intensywnej powtórki';
    }

    $rec = "Świetna forma! Utrzymuj regularność rozwiązując 1 próbny test tygodniowo.";
    if ($weakestCat !== null && $lowestScore < 60) {
        $rec = "Skup się na powtórce działu [{$weakestCat}] (skuteczność {$lowestScore}%). Skorzystaj z trybu fiszek SM-2 lub laboratorium.";
    }

    return [
        'overall_score' => (int)round($avgScore),
        'pass_probability' => $passProbability,
        'tests_count' => count($recentTests),
        'category_scores' => $categoryScores,
        'readiness_label' => $label,
        'weakest_category' => $weakestCat,
        'recommendation' => $rec,
    ];
}
