<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/CourseService.php';

startSecureSession();
securityApplyJsonHeaders();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        securitySendJson(['success' => false, 'message' => 'Tylko zapytania POST są dozwolone.'], 405);
    }

    $currentUser = getCurrentUser();
    if (!$currentUser) {
        securitySendJson(['success' => false, 'message' => 'Wymagane logowanie.'], 401);
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $csrfToken = (string)($data['csrf_token'] ?? '');
    if (!validateCsrfToken($csrfToken, 'course_cli_validate') && !validateCsrfToken($csrfToken, 'general')) {
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) && !validateCsrfToken($csrfToken, 'course_cli_validate')) {
            securitySendJson(['success' => false, 'message' => 'Błąd weryfikacji tokenu CSRF.'], 403);
        }
    }

    $itemId = (int)($data['item_id'] ?? 0);
    $taskId = trim((string)($data['task_id'] ?? ''));
    $command = trim((string)($data['command'] ?? ''));

    if ($itemId <= 0 || $taskId === '' || $command === '') {
        securitySendJson(['success' => false, 'message' => 'Brak wymaganych parametrów zadania CLI.'], 422);
    }

    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once __DIR__ . '/../config/db.php';
    }

    $item = courseFetchItem($pdo, $itemId);
    if (!$item) {
        securitySendJson(['success' => false, 'message' => 'Lekcja nie istnieje.'], 404);
    }

    $document = courseDecodeLessonDocument($item['content'] ?? null);
    $targetBlock = null;
    if ($document && isset($document['blocks'])) {
        foreach ($document['blocks'] as $block) {
            if ($block['type'] === 'cli_task' && (string)($block['task_id'] ?? '') === $taskId) {
                $targetBlock = $block;
                break;
            }
        }
    }

    $expectedCmd = $targetBlock ? trim((string)($targetBlock['target_command'] ?? '')) : '';
    $expectedOutput = $targetBlock ? (string)($targetBlock['expected_output'] ?? 'Polecenie wykonane pomyślnie.') : 'OK';
    $xpReward = $targetBlock ? (int)($targetBlock['xp_reward'] ?? 15) : 10;

    $normUserCmd = strtolower(preg_replace('/\s+/', ' ', $command));
    $normExpectedCmd = strtolower(preg_replace('/\s+/', ' ', $expectedCmd));

    $isCorrect = false;
    if ($normExpectedCmd !== '') {
        $isCorrect = ($normUserCmd === $normExpectedCmd) || (str_contains($normUserCmd, $normExpectedCmd));
    } else {
        $isCorrect = mb_strlen($normUserCmd) >= 3;
    }

    if (!$isCorrect) {
        securitySendJson([
            'success' => false,
            'message' => 'Polecenie nie pasuje do wzorca zadania. Sprawdź składnię i spróbuj ponownie.',
        ], 200);
    }

    try {
        awardUserXp((int)$currentUser['id'], $xpReward, "CLI Lab: " . ($targetBlock['title'] ?? 'Zadanie CLI'));
    } catch (Throwable $e) {}

    securitySendJson([
        'success' => true,
        'output' => $expectedOutput ?: "Polecenie wykonane prawidłowo.\n[OK] Sukces!",
        'xp_awarded' => $xpReward,
        'message' => 'Zadanie wykonane pomyślnie! +' . $xpReward . ' XP',
    ], 200);
} catch (Throwable $e) {
    securitySendJson([
        'success' => false,
        'message' => 'Błąd serwera: ' . $e->getMessage(),
    ], 500);
}
