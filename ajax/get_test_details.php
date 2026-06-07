<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

// Ensure user is logged in
startSecureSession();
securityApplyResponseHeaders();
if (!isLoggedIn()) {
    echo '<div class="alert alert-danger">Nie jesteś zalogowany.</div>';
    exit;
}

$userId = (int)$_SESSION['user_id'];
$testId = securityInputInt($_GET['id'] ?? 0, 0, PHP_INT_MAX, 0);

if ($testId <= 0) {
    echo '<div class="alert alert-danger">Nieprawidłowy identyfikator testu.</div>';
    exit;
}

$limit = securityConsumeRateLimit('test-details:' . securityActorKey(), 80, 60);
if (empty($limit['allowed'])) {
    http_response_code(429);
    echo '<div class="alert alert-warning">Zbyt wiele odświeżeń szczegółów. Spróbuj za chwilę.</div>';
    exit;
}

try {
    // Verify the test belongs to the user
    $stmt = $pdo->prepare("SELECT id, mode, score_percent, correct_answers, total_questions, time_spent, test_date FROM test_results WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => $testId, 'user_id' => $userId]);
    $testResult = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$testResult) {
        echo '<div class="alert alert-danger">Nie znaleziono testu lub brak uprawnień.</div>';
        exit;
    }

    // Dodatkowe zapytanie po pełne dane do statystyk, jeśli potrzebne (opcjonalnie)
    
    echo '<div class="test-details-container">';
    echo '  <ul class="list-group list-group-flush mb-4">';
    echo '      <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="bi bi-calendar3 text-muted me-2"></i> Data testu:</span> <strong>' . htmlspecialchars($testResult['test_date']) . '</strong></li>';
    echo '      <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="bi bi-bullseye text-muted me-2"></i> Wynik:</span> <strong class="text-primary">' . htmlspecialchars(round($testResult['score_percent'], 1)) . '%</strong></li>';
    echo '      <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="bi bi-check-circle text-success me-2"></i> Poprawne:</span> <strong>' . (int)$testResult['correct_answers'] . ' / ' . (int)$testResult['total_questions'] . '</strong></li>';
    echo '      <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="bi bi-clock text-muted me-2"></i> Czas trwania:</span> <strong>' . gmdate("H:i:s", (int)$testResult['time_spent']) . '</strong></li>';
    echo '  </ul>';
    
    // Pobieranie odpowiedzi z bazy (tabela test_answers wg schematu)
    $answersStmt = $pdo->prepare("
        SELECT ta.question_id, ta.user_answer, ta.correct_answer, ta.is_correct,
               q.question_text, q.option_a, q.option_b, q.option_c, q.option_d
        FROM test_answers ta
        LEFT JOIN questions q ON ta.question_id = q.id
        WHERE ta.result_id = :result_id
        ORDER BY ta.id ASC
    ");
    $answersStmt->execute(['result_id' => $testId]);
    $answers = $answersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fallback tylko dla pytań bez danych z JOIN, bez ładowania całego banku.
    $questionsMap = [];
    $missingQuestionIds = [];
    foreach ($answers as $ans) {
        if (empty($ans['question_text'])) {
            $missingQuestionIds[] = (int)$ans['question_id'];
        }
    }
    if ($missingQuestionIds) {
        foreach (getQuestionsByIds($pdo, array_values(array_unique($missingQuestionIds))) as $q) {
            $questionsMap[(int)$q['id']] = $q;
        }
    }

    if (count($answers) > 0) {
        echo '  <h6 class="border-bottom pb-2 mb-3">Twoje odpowiedzi:</h6>';
        echo '  <div class="list-group list-group-flush">';
        foreach ($answers as $index => $ans) {
            $statusIcon = $ans['is_correct'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>';
            $bgColor = $ans['is_correct'] ? 'bg-success bg-opacity-10' : 'bg-danger bg-opacity-10';
            
            // Mapowanie tekstu pytania i opcji, jeśli brakuje ich w bazie danych
            $qText = $ans['question_text'];
            $qOptA = $ans['option_a'];
            $qOptB = $ans['option_b'];
            $qOptC = $ans['option_c'];
            $qOptD = $ans['option_d'];

            if (empty($qText) && isset($questionsMap[$ans['question_id']])) {
                $mappedQ = $questionsMap[$ans['question_id']];
                $qText = $mappedQ['question_text'];
                $qOptA = $mappedQ['option_a'];
                $qOptB = $mappedQ['option_b'];
                $qOptC = $mappedQ['option_c'];
                $qOptD = $mappedQ['option_d'];
            }

            echo '  <div class="list-group-item py-3 ' . $bgColor . ' rounded mb-2 test-answer-detail">';
            echo '      <div class="d-flex align-items-center justify-content-between gap-2 mb-2">';
            echo '          <strong class="d-inline-flex align-items-center gap-2 flex-nowrap"><span class="answer-index-badge">Pytanie ' . ($index + 1) . '</span>' . $statusIcon . '</strong>';
            echo '      </div>';
            echo '      <p class="mb-2">' . htmlspecialchars($qText ?? 'Brak treści pytania (usunięte?)') . '</p>';
            
            $options = [
                'A' => $qOptA,
                'B' => $qOptB,
                'C' => $qOptC,
                'D' => $qOptD
            ];
            
            echo '      <div class="small mb-1">';
            echo '          <span class="text-muted">Twoja odpowiedź:</span> <strong class="' . ($ans['is_correct'] ? 'text-success' : 'text-danger') . '">' . htmlspecialchars($ans['user_answer']) . '</strong>';
            if (!empty($options[$ans['user_answer']])) {
                echo ' (' . htmlspecialchars($options[$ans['user_answer']]) . ')';
            }
            echo '      </div>';
            
            if (!$ans['is_correct']) {
                echo '  <div class="small">';
                echo '      <span class="text-muted">Poprawna odpowiedź:</span> <strong class="text-success">' . htmlspecialchars($ans['correct_answer']) . '</strong>';
                if (!empty($options[$ans['correct_answer']])) {
                    echo ' (' . htmlspecialchars($options[$ans['correct_answer']]) . ')';
                }
                echo '  </div>';
            }
            echo '  </div>';
        }
        echo '  </div>';
    } else {
        echo '  <p class="text-muted text-center my-4">Brak zapisanych odpowiedzi dla tego testu.</p>';
    }
    echo '</div>';

} catch (PDOException $e) {
    error_log("Error in get_test_details.php: " . $e->getMessage());
    securityAudit('get_test_details_failed', ['test_id' => $testId, 'user_id' => $userId], 'error');
    echo '<div class="alert alert-danger">Wystąpił błąd bazy danych.</div>';
}
