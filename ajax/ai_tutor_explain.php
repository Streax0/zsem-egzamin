<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/AiTutorEngine.php';

startSecureSession();
securityApplyJsonHeaders();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        securitySendJson(['success' => false, 'message' => 'Wymagane zapytanie POST.'], 405);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $questionId = (int)($data['question_id'] ?? 0);

    if ($questionId <= 0) {
        securitySendJson(['success' => false, 'message' => 'Nieprawidłowe ID pytania.'], 422);
    }

    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once __DIR__ . '/../config/db.php';
    }

    $stmt = $pdo->prepare("SELECT id, category, question_text, option_a, option_b, option_c, option_d FROM questions WHERE id = ? LIMIT 1");
    $stmt->execute([$questionId]);
    $q = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$q) {
        $all = loadQuestions($pdo, true);
        foreach ($all as $item) {
            if ((int)($item['id'] ?? 0) === $questionId) {
                $q = [
                    'id' => $questionId,
                    'category' => $item['category'] ?? 'Ogólne',
                    'question_text' => $item['question'] ?? ($item['question_text'] ?? ''),
                    'option_a' => $item['answer_a'] ?? ($item['option_a'] ?? ($item['answers'][0] ?? '')),
                    'option_b' => $item['answer_b'] ?? ($item['option_b'] ?? ($item['answers'][1] ?? '')),
                    'option_c' => $item['answer_c'] ?? ($item['option_c'] ?? ($item['answers'][2] ?? '')),
                    'option_d' => $item['answer_d'] ?? ($item['option_d'] ?? ($item['answers'][3] ?? '')),
                ];
                break;
            }
        }
    }

    if (!$q) {
        securitySendJson(['success' => false, 'message' => 'Pytanie nie istnieje w bazie.'], 404);
    }

    $category = (string)($q['category'] ?? 'Ogólne');
    $questionText = (string)($q['question_text'] ?? ($q['question'] ?? ''));

    $opts = [
        (string)($q['option_a'] ?? ($q['answer_a'] ?? '')),
        (string)($q['option_b'] ?? ($q['answer_b'] ?? '')),
        (string)($q['option_c'] ?? ($q['answer_c'] ?? '')),
        (string)($q['option_d'] ?? ($q['answer_d'] ?? '')),
    ];

    // Generate Socratic guidance (ZERO answers revealed)
    $socraticHint = aiTutorGenerateSocraticHint($questionText, $category, $opts);

    securitySendJson([
        'success' => true,
        'question_id' => $questionId,
        'category' => $category,
        'mode' => 'socratic',
        'topic' => $socraticHint['topic'],
        'guiding_question' => $socraticHint['guiding_question'],
        'concept_refresher' => $socraticHint['concept_refresher'],
        'trap_to_avoid' => $socraticHint['trap_to_avoid'],
    ], 200);
} catch (Throwable $e) {
    securitySendJson([
        'success' => false,
        'message' => 'Wystąpił błąd podczas pobierania wskazówki: ' . $e->getMessage(),
    ], 500);
}
