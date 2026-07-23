<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

if (!isLoggedIn()) {
    echo securityJsonEncode(['success' => false, 'message' => 'Musisz być zalogowany, aby zapisywać postępy.']);
    exit;
}

$action = $_POST['action'] ?? '';
$token = $_POST['csrf_token'] ?? '';

if (!validateCsrfToken($token, 'course_progress')) {
    echo securityJsonEncode(['success' => false, 'message' => 'Nieprawidłowy token CSRF.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Helper function to update overall course progress
function updateCourseEnrollmentProgress(PDO $pdo, int $userId, int $courseId): int {
    // 1. Get all items for this course
    $stmt = $pdo->prepare("
        SELECT ci.id 
        FROM course_items ci
        JOIN course_modules cm ON cm.id = ci.module_id
        WHERE cm.course_id = ?
    ");
    $stmt->execute([$courseId]);
    $allItems = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $totalCount = count($allItems);

    if ($totalCount === 0) {
        return 0;
    }

    // 2. Get completed items count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_course_progress 
        WHERE user_id = ? AND course_id = ? AND status = 'completed' AND item_id IN (" . implode(',', array_map('intval', $allItems)) . ")
    ");
    $stmt->execute([$userId, $courseId]);
    $completedCount = (int)$stmt->fetchColumn();

    $percent = (int)round(($completedCount / $totalCount) * 100);
    $percent = max(0, min(100, $percent));

    // 3. Update enrollment
    $status = ($percent === 100) ? 'completed' : 'active';
    $completedAtSql = ($percent === 100) ? ", completed_at = COALESCE(completed_at, NOW())" : ", completed_at = NULL";

    $updateStmt = $pdo->prepare("
        UPDATE user_course_enrollments 
        SET progress_percent = ?, status = ? $completedAtSql
        WHERE user_id = ? AND course_id = ?
    ");
    $updateStmt->execute([$percent, $status, $userId, $courseId]);

    return $percent;
}

try {
    switch ($action) {
        case 'mark_completed':
            $itemId = (int)($_POST['item_id'] ?? 0);
            if ($itemId <= 0) {
                echo securityJsonEncode(['success' => false, 'message' => 'Nieprawidłowa lekcja.']);
                exit;
            }

            // Fetch module details to get course_id and sequential settings
            $stmt = $pdo->prepare("
                SELECT ci.id, ci.type, cm.course_id, c.sequential_learning, ci.sort_order, cm.sort_order as module_sort
                FROM course_items ci
                JOIN course_modules cm ON cm.id = ci.module_id
                JOIN courses c ON c.id = cm.course_id
                WHERE ci.id = ? LIMIT 1
            ");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                echo securityJsonEncode(['success' => false, 'message' => 'Lekcja nie istnieje.']);
                exit;
            }

            $courseId = (int)$item['course_id'];

            // Verify enrollment
            $enrollStmt = $pdo->prepare("SELECT id FROM user_course_enrollments WHERE user_id = ? AND course_id = ?");
            $enrollStmt->execute([$userId, $courseId]);
            if (!$enrollStmt->fetchColumn()) {
                echo securityJsonEncode(['success' => false, 'message' => 'Nie jesteś zapisany na ten kurs.']);
                exit;
            }

            // Sequential Learning Check
            if ((int)$item['sequential_learning'] === 1) {
                // Get all items in this course sorted by module and item sort order
                $allStmt = $pdo->prepare("
                    SELECT ci.id 
                    FROM course_items ci
                    JOIN course_modules cm ON cm.id = ci.module_id
                    WHERE cm.course_id = ?
                    ORDER BY cm.sort_order ASC, ci.sort_order ASC
                ");
                $allStmt->execute([$courseId]);
                $orderedItems = $allStmt->fetchAll(PDO::FETCH_COLUMN);

                $currentIndex = array_search($itemId, $orderedItems);
                if ($currentIndex > 0) {
                    // Check if all previous items are completed
                    $prevItems = array_slice($orderedItems, 0, $currentIndex);
                    $checkPrevStmt = $pdo->prepare("
                        SELECT COUNT(*) 
                        FROM user_course_progress 
                        WHERE user_id = ? AND item_id IN (" . implode(',', array_map('intval', $prevItems)) . ") AND status = 'completed'
                    ");
                    $checkPrevStmt->execute([$userId]);
                    $completedPrevCount = (int)$checkPrevStmt->fetchColumn();

                    if ($completedPrevCount < count($prevItems)) {
                        echo securityJsonEncode(['success' => false, 'message' => 'Musisz najpierw ukończyć poprzednie lekcje.']);
                        exit;
                    }
                }
            }

            // Insert or update progress
            $progStmt = $pdo->prepare("
                INSERT INTO user_course_progress (user_id, course_id, item_id, status, completed_at)
                VALUES (?, ?, ?, 'completed', NOW())
                ON DUPLICATE KEY UPDATE status = 'completed', completed_at = COALESCE(completed_at, NOW())
            ");
            $progStmt->execute([$userId, $courseId, $itemId]);

            $progressPercent = updateCourseEnrollmentProgress($pdo, $userId, $courseId);

            echo securityJsonEncode(['success' => true, 'message' => 'Lekcja oznaczona jako ukończona.', 'progress_percent' => $progressPercent]);
            break;

        case 'submit_quiz':
            $itemId = (int)($_POST['item_id'] ?? 0);
            $answers = $_POST['answers'] ?? []; // Array of [question_id => selected_option]

            if ($itemId <= 0 || !is_array($answers)) {
                echo securityJsonEncode(['success' => false, 'message' => 'Błędne dane quizu.']);
                exit;
            }

            // Get item details
            $itemStmt = $pdo->prepare("
                SELECT ci.id, ci.quiz_passing_score, cm.course_id
                FROM course_items ci
                JOIN course_modules cm ON cm.id = ci.module_id
                WHERE ci.id = ? AND ci.type IN ('quiz', 'exam') LIMIT 1
            ");
            $itemStmt->execute([$itemId]);
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                echo securityJsonEncode(['success' => false, 'message' => 'Quiz nie istnieje.']);
                exit;
            }

            $courseId = (int)$item['course_id'];
            $passingScore = (int)$item['quiz_passing_score'];

            // Get questions for this quiz
            $qStmt = $pdo->prepare("SELECT * FROM course_quiz_questions WHERE item_id = ?");
            $qStmt->execute([$itemId]);
            $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($questions)) {
                echo securityJsonEncode(['success' => false, 'message' => 'Ten quiz nie zawiera pytań.']);
                exit;
            }

            $totalQuestions = count($questions);
            $correctCount = 0;
            $details = [];

            foreach ($questions as $q) {
                $qId = (int)$q['id'];
                $userAns = trim(strtoupper((string)($answers[$qId] ?? '')));
                $correctAns = trim(strtoupper($q['correct_answer']));
                $isCorrect = ($userAns === $correctAns);

                if ($isCorrect) {
                    $correctCount++;
                }

                $details[$qId] = [
                    'user_answer' => $userAns,
                    'correct_answer' => $correctAns,
                    'is_correct' => $isCorrect,
                    'explanation' => $q['explanation'] ?? ''
                ];
            }

            $scorePercent = (int)round(($correctCount / $totalQuestions) * 100);
            $passed = ($scorePercent >= $passingScore);

            // Update user attempts and score in database
            // Get current progress if exists
            $checkProgStmt = $pdo->prepare("SELECT quiz_attempts, quiz_score, status FROM user_course_progress WHERE user_id = ? AND item_id = ?");
            $checkProgStmt->execute([$userId, $itemId]);
            $currentProg = $checkProgStmt->fetch(PDO::FETCH_ASSOC);

            $attempts = $currentProg ? (int)$currentProg['quiz_attempts'] + 1 : 1;
            $bestScore = $currentProg ? max((int)$currentProg['quiz_score'], $scorePercent) : $scorePercent;
            $currentStatus = $currentProg ? $currentProg['status'] : 'started';
            
            // If already completed once, keep completed status even if they failed a retry
            $newStatus = ($passed || $currentStatus === 'completed') ? 'completed' : 'started';
            $completedAtSql = ($newStatus === 'completed' && ($currentStatus !== 'completed' || !$currentProg)) ? ", completed_at = NOW()" : "";

            $progUpdateStmt = $pdo->prepare("
                INSERT INTO user_course_progress (user_id, course_id, item_id, status, quiz_score, quiz_attempts, completed_at)
                VALUES (?, ?, ?, ?, ?, ?, CASE WHEN ? = 'completed' THEN NOW() ELSE NULL END)
                ON DUPLICATE KEY UPDATE 
                    status = ?, 
                    quiz_score = ?, 
                    quiz_attempts = ?, 
                    completed_at = CASE WHEN ? = 'completed' THEN COALESCE(completed_at, NOW()) ELSE completed_at END
            ");
            $progUpdateStmt->execute([
                $userId, $courseId, $itemId, $newStatus, $bestScore, $attempts, $newStatus,
                $newStatus, $bestScore, $attempts, $newStatus
            ]);

            $progressPercent = updateCourseEnrollmentProgress($pdo, $userId, $courseId);

            echo securityJsonEncode([
                'success' => true,
                'passed' => $passed,
                'score_percent' => $scorePercent,
                'passing_score' => $passingScore,
                'correct_count' => $correctCount,
                'total_count' => $totalQuestions,
                'attempts' => $attempts,
                'details' => $details,
                'progress_percent' => $progressPercent
            ]);
            break;

        default:
            echo securityJsonEncode(['success' => false, 'message' => 'Nieznana akcja.']);
            break;
    }
} catch (Exception $e) {
    error_log("AJAX course progress failed: " . $e->getMessage());
    echo securityJsonEncode(['success' => false, 'message' => 'Wystąpił błąd serwera. Spróbuj ponownie.']);
}
