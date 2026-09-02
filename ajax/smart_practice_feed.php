<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        securitySendJson(['success' => false, 'message' => 'Niedozwolona metoda HTTP.'], 405);
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once __DIR__ . '/../config/db.php';
    }

    $limit = max(5, min(50, (int)($_GET['limit'] ?? $_POST['limit'] ?? 20)));
    $category = trim((string)($_GET['category'] ?? $_POST['category'] ?? ''));

    $questions = [];

    if ($userId > 0) {
        try {
            $recentResultsStmt = $pdo->prepare("SELECT id FROM test_results WHERE user_id = ? ORDER BY id DESC LIMIT 20");
            $recentResultsStmt->execute([$userId]);
            $recentResultIds = $recentResultsStmt->fetchAll(PDO::FETCH_COLUMN);

            $sql = "
                SELECT q.id, q.category, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer, q.explanation, q.image_url,
                       COALESCE(MAX(sm2.easiness_factor), 2.5) AS easiness_factor,
                       COALESCE(MAX(sm2.repetition_count), 0) AS repetition_count,
                       COUNT(ta.id) AS fail_count
                FROM questions q
                LEFT JOIN flashcard_sm2 sm2 ON sm2.user_id = :uid1 AND sm2.card_key = CONCAT('q_', q.id)
            ";

            $params = [':uid1' => $userId];
            if (!empty($recentResultIds)) {
                $placeholders = implode(',', array_map('intval', $recentResultIds));
                $sql .= " LEFT JOIN test_answers ta ON ta.question_id = q.id AND ta.is_correct = 0 AND ta.result_id IN ($placeholders) ";
            } else {
                $sql .= " LEFT JOIN test_answers ta ON 1=0 ";
            }

            $sql .= " WHERE 1=1 ";
            if ($category !== '') {
                $sql .= " AND q.category = :category ";
                $params[':category'] = $category;
            }

            $sql .= "
                GROUP BY q.id, q.category, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer, q.explanation, q.image_url
                ORDER BY (COUNT(ta.id) * 3 + (CASE WHEN MAX(sm2.next_review_date) <= CURDATE() THEN 5 ELSE 0 END)) DESC, RAND()
                LIMIT :lim
            ";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $questions = [];
        }
    }

    if (empty($questions)) {
        $sql = "SELECT id, category, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation, image_url FROM questions WHERE 1=1";
        $params = [];
        if ($category !== '') {
            $sql .= " AND category = :category";
            $params[':category'] = $category;
        }
        $sql .= " ORDER BY RAND() LIMIT :lim";
        $stmt = $pdo->prepare($sql);
        if ($category !== '') {
            $stmt->bindValue(':category', $category);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $cleanQuestions = [];
    foreach ($questions as $q) {
        $cleanQuestions[] = [
            'id' => (int)$q['id'],
            'category' => (string)($q['category'] ?? 'Ogólne'),
            'question' => (string)($q['question_text'] ?? ($q['question'] ?? '')),
            'answer_a' => (string)($q['option_a'] ?? ($q['answer_a'] ?? '')),
            'answer_b' => (string)($q['option_b'] ?? ($q['answer_b'] ?? '')),
            'answer_c' => (string)($q['option_c'] ?? ($q['answer_c'] ?? '')),
            'answer_d' => (string)($q['option_d'] ?? ($q['answer_d'] ?? '')),
            'correct_answer' => (string)$q['correct_answer'],
            'explanation' => (string)($q['explanation'] ?? ''),
            'image_url' => (string)($q['image_url'] ?? ''),
            'is_weak_spot' => ((int)($q['fail_count'] ?? 0) > 0),
        ];
    }

    securitySendJson([
        'success' => true,
        'count' => count($cleanQuestions),
        'mode' => 'sm2_spaced_repetition',
        'questions' => $cleanQuestions,
    ], 200);
} catch (Throwable $e) {
    securitySendJson([
        'success' => false,
        'message' => 'Błąd pobierania pytań: ' . $e->getMessage(),
    ], 500);
}
