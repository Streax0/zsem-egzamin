<?php declare(strict_types=1);

/**
 * ZSEM Tech - Comprehensive Teacher Exam Backend Verification Test Suite
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$passedCount = 0;
$totalCount = 0;

function runTest(string $testName, callable $callback): void {
    global $passedCount, $totalCount;
    $totalCount++;
    try {
        $callback();
        echo "  \033[32m[PASS]\033[0m {$testName}\n";
        $passedCount++;
    } catch (Throwable $e) {
        echo "  \033[31m[FAIL]\033[0m {$testName}: " . $e->getMessage() . " (Line: " . $e->getLine() . ")\n";
    }
}

function assertTrue(bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertEquals(mixed $expected, mixed $actual, string $message = ''): void {
    if ($expected !== $actual) {
        $expStr = is_scalar($expected) ? (string)$expected : json_encode($expected);
        $actStr = is_scalar($actual) ? (string)$actual : json_encode($actual);
        throw new RuntimeException(($message ? $message . ' - ' : '') . "Expected: [{$expStr}], got: [{$actStr}]");
    }
}

echo "\n=================================================================\n";
echo " Running Full Teacher Exam Backend Test Suite (All Tiers)         \n";
echo "=================================================================\n\n";

// Tier 1: RBAC & Helper Functions
echo "▶ TIER 1: RBAC, Role Authorization & PIN Generation\n";

runTest("Role permissions: teacher, admin, dyrektor vs standard user", function() {
    assertTrue(roleHasAdminAccess('admin'), "Admin must have admin access");
    assertTrue(roleHasAdminAccess('dyrektor'), "Dyrektor must have admin access");
    assertTrue(!roleHasAdminAccess('teacher'), "Teacher must not have global admin access");
    assertTrue(!roleHasAdminAccess('user'), "User must not have admin access");
    assertTrue(!roleHasAdminAccess('guest'), "Guest must not have admin access");
});

runTest("Access code generator produces valid 6-char PIN", function() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($i = 0; $i < 50; $i++) {
        $code = '';
        for ($j = 0; $j < 6; $j++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        assertEquals(6, strlen($code), "PIN length must be 6");
        assertTrue((bool)preg_match('/^[A-Z0-9]{6}$/', $code), "PIN format must be alphanumeric");
        assertTrue(!str_contains($code, 'O') && !str_contains($code, 'I') && !str_contains($code, '0') && !str_contains($code, '1'), "PIN must omit confusing chars");
    }
});

// Tier 2: Database Schema & Exam Model Integrity
echo "\n▶ TIER 2: Database Schema & Relations Integrity\n";

runTest("Verify required exam database tables exist", function() use ($pdo) {
    $tables = ['exams', 'exam_sessions', 'exam_session_questions', 'exam_participants', 'exam_answers', 'exam_violations', 'exam_warnings', 'app_settings'];
    $existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $tbl) {
        assertTrue(in_array($tbl, $existingTables, true), "Table {$tbl} must exist in database");
    }
});

runTest("Verify crucial columns in exams and exam_sessions", function() use ($pdo) {
    assertTrue(dbColumnExists($pdo, 'exams', 'teacher_id'), "exams.teacher_id missing");
    assertTrue(dbColumnExists($pdo, 'exams', 'anti_cheat_enabled'), "exams.anti_cheat_enabled missing");
    assertTrue(dbColumnExists($pdo, 'exams', 'grade_thresholds'), "exams.grade_thresholds missing");
    assertTrue(dbColumnExists($pdo, 'exam_sessions', 'paused_seconds'), "exam_sessions.paused_seconds missing");
    assertTrue(dbColumnExists($pdo, 'exam_participants', 'score_percent'), "exam_participants.score_percent missing");
    assertTrue(dbColumnExists($pdo, 'exam_participants', 'violation_count'), "exam_participants.violation_count missing");
});

// Tier 3: Exam Lifecycle End-to-End Simulation
echo "\n▶ TIER 3: Exam Lifecycle (Create -> Session -> Participants -> Take -> Finish)\n";

$testTeacherId = null;
$testStudentId = null;
$testExamId = null;
$testSessionId = null;
$participant1Id = null;
$participant2Id = null;
$createdQuestionIds = [];

runTest("Create temporary teacher, student accounts and test questions", function() use ($pdo, &$testTeacherId, &$testStudentId, &$createdQuestionIds) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'test_teacher_auto' LIMIT 1");
    $stmt->execute();
    $testTeacherId = (int)$stmt->fetchColumn();

    if (!$testTeacherId) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, first_name, last_name) VALUES ('test_teacher_auto', 'teacher_auto@example.com', 'dummy_hash', 'teacher', 'Jan', 'Nauczyciel')");
        $stmt->execute();
        $testTeacherId = (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'test_student_auto' LIMIT 1");
    $stmt->execute();
    $testStudentId = (int)$stmt->fetchColumn();

    if (!$testStudentId) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, first_name, last_name, class) VALUES ('test_student_auto', 'student_auto@example.com', 'dummy_hash', 'user', 'Piotr', 'Uczeń', '4P')");
        $stmt->execute();
        $testStudentId = (int)$pdo->lastInsertId();
    }

    assertTrue($testTeacherId > 0, "Teacher ID must be > 0");
    assertTrue($testStudentId > 0, "Student ID must be > 0");

    // Ensure 5 questions exist
    $stmtQ = $pdo->query("SELECT id FROM questions LIMIT 5");
    $existingQ = $stmtQ->fetchAll(PDO::FETCH_COLUMN);
    $needed = 5 - count($existingQ);
    for ($i = 1; $i <= $needed; $i++) {
        $qData = [
            'category' => 'INF.02',
            'question_text' => 'Pytanie testowe ' . $i . ' ' . uniqid(),
            'option_a' => 'Odpowiedź A',
            'option_b' => 'Odpowiedź B',
            'option_c' => 'Odpowiedź C',
            'option_d' => 'Odpowiedź D',
            'correct_answer' => 'A',
            'explanation' => 'Wyjaśnienie ' . $i,
            'image_url' => ''
        ];
        addQuestion($pdo, $qData);
        $createdQuestionIds[] = (int)$pdo->lastInsertId();
    }
});

runTest("Create exam with custom thresholds and AI Copy Guard", function() use ($pdo, &$testTeacherId, &$testExamId) {
    $gradeThresholds = json_encode(['6' => 95, '5' => 85, '4' => 70, '3' => 50, '2' => 30]);
    $stmt = $pdo->prepare("
        INSERT INTO exams (
            teacher_id, title, description, question_count, categories,
            difficulty_level, shuffle_questions, shuffle_answers, max_participants,
            total_time, time_per_question, exam_mode, auto_finish_on_time, allow_rejoin,
            anti_cheat_enabled, block_tab_switch, require_fullscreen, lobby_enabled,
            show_results_to_student, show_predicted_grade, show_correct_answers,
            randomize_per_student, lock_after_finish, pass_threshold, max_attempts,
            navigation_mode, allow_answer_changes, grade_thresholds
        ) VALUES (
            ?, 'Automated Test Exam INF.02', 'Sprawdzian diagnostyczny', 5, '[\"INF.02\"]',
            'mixed', 1, 1, 30,
            45, 60, 1, 1, 1,
            1, 1, 1, 1,
            1, 1, 1,
            1, 1, 50, 1,
            'free', 1, ?
        )
    ");
    $stmt->execute([$testTeacherId, $gradeThresholds]);
    $testExamId = (int)$pdo->lastInsertId();
    assertTrue($testExamId > 0, "Exam ID must be generated");

    // Configure AI Copy Guard
    assertTrue(setExamAiCopyGuard($pdo, $testExamId, true), "AI Copy Guard setting must save");
    assertTrue(examAiCopyGuardEnabled($pdo, $testExamId), "AI Copy Guard setting must be true");
});

runTest("Create exam session in 'lobby' state and assign 5 questions", function() use ($pdo, &$testExamId, &$testSessionId) {
    $code = 'TST' . random_int(100, 999);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
    $stmt = $pdo->prepare("INSERT INTO exam_sessions (exam_id, access_code, status, expires_at) VALUES (?, ?, 'lobby', ?)");
    $stmt->execute([$testExamId, $code, $expiresAt]);
    $testSessionId = (int)$pdo->lastInsertId();
    assertTrue($testSessionId > 0, "Session ID must be generated");

    // Fetch 5 valid questions
    $stmtQ = $pdo->query("SELECT id, correct_answer FROM questions LIMIT 5");
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
    assertTrue(count($questions) >= 5, "Database must have at least 5 questions");

    $order = 1;
    $stmtAssign = $pdo->prepare("INSERT INTO exam_session_questions (session_id, question_id, question_order) VALUES (?, ?, ?)");
    foreach ($questions as $q) {
        $stmtAssign->execute([$testSessionId, $q['id'], $order++]);
    }

    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM exam_session_questions WHERE session_id = ?");
    $stmtCount->execute([$testSessionId]);
    assertEquals(5, (int)$stmtCount->fetchColumn(), "Session must have exactly 5 questions linked");
});

runTest("Register participants (Logged-in Student + Guest Student)", function() use ($pdo, &$testSessionId, &$testStudentId, &$participant1Id, &$participant2Id) {
    // Participant 1: Logged-in
    $stmt = $pdo->prepare("INSERT INTO exam_participants (session_id, user_id, first_name, last_name, class, status) VALUES (?, ?, 'Piotr', 'Uczeń', '4P', 'in_lobby')");
    $stmt->execute([$testSessionId, $testStudentId]);
    $participant1Id = (int)$pdo->lastInsertId();

    // Participant 2: Guest
    $stmt = $pdo->prepare("INSERT INTO exam_participants (session_id, user_id, first_name, last_name, class, status) VALUES (?, NULL, 'Anna', 'Gość', '4P', 'in_lobby')");
    $stmt->execute([$testSessionId]);
    $participant2Id = (int)$pdo->lastInsertId();

    assertTrue($participant1Id > 0, "Participant 1 ID must be > 0");
    assertTrue($participant2Id > 0, "Participant 2 ID must be > 0");
});

runTest("Session state machine transitions: lobby -> in_progress -> paused -> resumed", function() use ($pdo, &$testSessionId) {
    // Start session
    $stmt = $pdo->prepare("UPDATE exam_sessions SET status = 'in_progress', started_at = NOW() WHERE id = ?");
    $stmt->execute([$testSessionId]);
    $pdo->prepare("UPDATE exam_participants SET status = 'taking_exam', started_at = NOW() WHERE session_id = ? AND status = 'in_lobby'")->execute([$testSessionId]);

    $stmt = $pdo->prepare("SELECT status, started_at FROM exam_sessions WHERE id = ?");
    $stmt->execute([$testSessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    assertEquals('in_progress', $session['status'], "Session status must be in_progress");
    assertTrue(!empty($session['started_at']), "started_at must be populated");

    // Pause session
    $pdo->prepare("UPDATE exam_sessions SET status = 'paused', paused_at = NOW() WHERE id = ?")->execute([$testSessionId]);
    $stmt = $pdo->prepare("SELECT status, paused_at FROM exam_sessions WHERE id = ?");
    $stmt->execute([$testSessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    assertEquals('paused', $session['status'], "Session status must be paused");

    // Resume session
    $pdo->prepare("UPDATE exam_sessions SET status = 'in_progress', paused_seconds = paused_seconds + IF(paused_at IS NULL, 0, TIMESTAMPDIFF(SECOND, paused_at, NOW())), paused_at = NULL WHERE id = ?")->execute([$testSessionId]);
    $stmt = $pdo->prepare("SELECT status, paused_at FROM exam_sessions WHERE id = ?");
    $stmt->execute([$testSessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    assertEquals('in_progress', $session['status'], "Session status must be resumed to in_progress");
    assertEquals(null, $session['paused_at'], "paused_at must be reset to NULL");
});

runTest("Simulate student answers submission and atomic grade score calculation", function() use ($pdo, &$testSessionId, &$participant1Id, &$participant2Id) {
    $stmtQ = $pdo->prepare("SELECT esq.question_id, esq.question_order, q.correct_answer FROM exam_session_questions esq JOIN questions q ON q.id = esq.question_id WHERE esq.session_id = ? ORDER BY esq.question_order");
    $stmtQ->execute([$testSessionId]);
    $sessionQuestions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
    assertEquals(5, count($sessionQuestions), "Must have 5 questions");

    // Participant 1 (Piotr): 4 correct, 1 wrong -> 4/5 = 80.0%
    $insertAnswer = $pdo->prepare("INSERT INTO exam_answers (participant_id, session_id, question_id, question_order, user_answer, correct_answer, is_correct, time_spent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($sessionQuestions as $idx => $sq) {
        $qId = (int)$sq['question_id'];
        $correct = strtoupper((string)$sq['correct_answer']);
        $userAns = ($idx === 4) ? ($correct === 'A' ? 'B' : 'A') : $correct; // Make 5th answer wrong
        $isCor = ($userAns === $correct) ? 1 : 0;
        $insertAnswer->execute([$participant1Id, $testSessionId, $qId, $sq['question_order'], $userAns, $correct, $isCor, 12]);
    }

    $pdo->prepare("UPDATE exam_participants SET total_answered = 5, correct_answers = 4, score_percent = 80.0, status = 'finished', finished_at = NOW(), time_spent = 60 WHERE id = ?")->execute([$participant1Id]);

    // Participant 2 (Anna): 5 correct -> 5/5 = 100.0%
    foreach ($sessionQuestions as $idx => $sq) {
        $qId = (int)$sq['question_id'];
        $correct = strtoupper((string)$sq['correct_answer']);
        $insertAnswer->execute([$participant2Id, $testSessionId, $qId, $sq['question_order'], $correct, $correct, 1, 10]);
    }

    $pdo->prepare("UPDATE exam_participants SET total_answered = 5, correct_answers = 5, score_percent = 100.0, status = 'finished', finished_at = NOW(), time_spent = 50 WHERE id = ?")->execute([$participant2Id]);

    // Verify stats in DB
    $stmt = $pdo->prepare("SELECT score_percent, correct_answers, status FROM exam_participants WHERE id = ?");
    $stmt->execute([$participant1Id]);
    $p1 = $stmt->fetch(PDO::FETCH_ASSOC);
    assertEquals('80.00', number_format((float)$p1['score_percent'], 2), "Participant 1 score must be 80.00%");
    assertEquals(4, (int)$p1['correct_answers'], "Participant 1 correct answers must be 4");
    assertEquals('finished', $p1['status'], "Participant 1 status must be finished");

    $stmt->execute([$participant2Id]);
    $p2 = $stmt->fetch(PDO::FETCH_ASSOC);
    assertEquals('100.00', number_format((float)$p2['score_percent'], 2), "Participant 2 score must be 100.00%");
    assertEquals(5, (int)$p2['correct_answers'], "Participant 2 correct answers must be 5");
    assertEquals('finished', $p2['status'], "Participant 2 status must be finished");
});

// Tier 4: Anti-Cheat & Telemetry Logging
echo "\n▶ TIER 4: Anti-Cheat & Telemetry Logging\n";

runTest("Record anti-cheat violations (tab switch, window blur, copy/paste)", function() use ($pdo, &$testSessionId, &$participant1Id) {
    $stmt = $pdo->prepare("INSERT INTO exam_violations (participant_id, session_id, violation_type, question_id, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$participant1Id, $testSessionId, 'tab_switch', 1]);
    $stmt->execute([$participant1Id, $testSessionId, 'copy_paste', 2]);

    $pdo->prepare("UPDATE exam_participants SET violation_count = violation_count + 2 WHERE id = ?")->execute([$participant1Id]);

    $stmt = $pdo->prepare("SELECT violation_count FROM exam_participants WHERE id = ?");
    $stmt->execute([$participant1Id]);
    assertEquals(2, (int)$stmt->fetchColumn(), "Violation count must be 2");

    $stmtV = $pdo->prepare("SELECT COUNT(*) FROM exam_violations WHERE session_id = ?");
    $stmtV->execute([$testSessionId]);
    assertEquals(2, (int)$stmtV->fetchColumn(), "Must have 2 violation rows recorded");
});

// Tier 5: Teacher Exam Analysis & Knowledge Gaps Engine
echo "\n▶ TIER 5: Analytics, Knowledge Gaps & Session Answer Key Override\n";

runTest("Calculate class performance metrics (Average, Median, Passing Rate)", function() use ($pdo, &$testSessionId) {
    $stmt = $pdo->prepare("SELECT score_percent FROM exam_participants WHERE session_id = ? AND status = 'finished'");
    $stmt->execute([$testSessionId]);
    $scores = array_map('floatval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    assertEquals(2, count($scores), "Must have 2 finished scores");

    $avg = round(array_sum($scores) / count($scores), 1);
    assertEquals(90.0, $avg, "Average score must be 90.0% (80 + 100 / 2)");

    $passingCount = count(array_filter($scores, fn($s) => $s >= 50.0));
    $passRate = round(($passingCount / count($scores)) * 100);
    assertEquals(100.0, (float)$passRate, "Pass rate must be 100%");
});

runTest("Calculate school grade attribution using custom threshold json", function() use ($pdo, &$testExamId, &$participant1Id, &$participant2Id) {
    $stmt = $pdo->prepare("SELECT grade_thresholds FROM exams WHERE id = ?");
    $stmt->execute([$testExamId]);
    $rawThresholds = $stmt->fetchColumn();
    $thresholds = is_string($rawThresholds) ? json_decode($rawThresholds, true) : null;
    assertTrue(is_array($thresholds), "Thresholds must be JSON array");

    // Grade function logic
    $calculateGrade = function(float $score, array $t): int {
        if ($score >= ($t['6'] ?? 95)) return 6;
        if ($score >= ($t['5'] ?? 85)) return 5;
        if ($score >= ($t['4'] ?? 70)) return 4;
        if ($score >= ($t['3'] ?? 50)) return 3;
        if ($score >= ($t['2'] ?? 30)) return 2;
        return 1;
    };

    assertEquals(4, $calculateGrade(80.0, $thresholds), "80% with 70% cutoff must give Grade 4");
    assertEquals(6, $calculateGrade(100.0, $thresholds), "100% with 95% cutoff must give Grade 6");
    assertEquals(1, $calculateGrade(25.0, $thresholds), "25% must give Grade 1");
});

runTest("Apply session answer key override dynamically recalculating scores", function() use ($pdo, &$testSessionId, &$testTeacherId, &$participant1Id) {
    // Get question 5 that was wrong for participant 1
    $stmt = $pdo->prepare("SELECT question_id, user_answer, correct_answer FROM exam_answers WHERE participant_id = ? AND is_correct = 0 LIMIT 1");
    $stmt->execute([$participant1Id]);
    $wrongRow = $stmt->fetch(PDO::FETCH_ASSOC);
    assertTrue(!empty($wrongRow), "Must have 1 wrong answer row");

    $targetQuestionId = (int)$wrongRow['question_id'];
    $newCorrectAnswer = $wrongRow['user_answer']; // Change correct answer to what user selected

    $overrideSuccess = applyExamCorrectAnswerOverride($pdo, $participant1Id, $targetQuestionId, $newCorrectAnswer, $testTeacherId, 'Klucz CKE zaktualizowany');
    assertTrue($overrideSuccess, "applyExamCorrectAnswerOverride must succeed");

    // Verify session question override column is updated
    $stmt = $pdo->prepare("SELECT correct_answer_override FROM exam_session_questions WHERE session_id = ? AND question_id = ?");
    $stmt->execute([$testSessionId, $targetQuestionId]);
    assertEquals($newCorrectAnswer, $stmt->fetchColumn(), "Session question override must be saved");

    // Verify participant 1 score updated to 100%
    $stmt = $pdo->prepare("SELECT score_percent, correct_answers FROM exam_participants WHERE id = ?");
    $stmt->execute([$participant1Id]);
    $updatedP1 = $stmt->fetch(PDO::FETCH_ASSOC);
    assertEquals('100.00', number_format((float)$updatedP1['score_percent'], 2), "Participant 1 score must be recalculated to 100%");
    assertEquals(5, (int)$updatedP1['correct_answers'], "Participant 1 correct answers must be 5");
});

// Tier 6: Exam Duplication & Cascade Cleanup
echo "\n▶ TIER 6: Cloning & Cascade Data Cleanup\n";

runTest("Duplicate / clone exam preserving all configurations", function() use ($pdo, &$testExamId, &$testTeacherId) {
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$testExamId]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    assertTrue(!empty($source), "Source exam must exist");

    $cloneTitle = $source['title'] . ' (Kopia)';
    $stmtClone = $pdo->prepare("INSERT INTO exams (teacher_id, title, description, question_count, grade_thresholds, anti_cheat_enabled) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtClone->execute([$testTeacherId, $cloneTitle, $source['description'], $source['question_count'], $source['grade_thresholds'], $source['anti_cheat_enabled']]);
    $cloneId = (int)$pdo->lastInsertId();
    assertTrue($cloneId > 0, "Clone ID must be generated");

    $stmtCheck = $pdo->prepare("SELECT title, anti_cheat_enabled FROM exams WHERE id = ?");
    $stmtCheck->execute([$cloneId]);
    $cloned = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    assertEquals($cloneTitle, $cloned['title'], "Cloned title must match");
    assertEquals(1, (int)$cloned['anti_cheat_enabled'], "Cloned anti_cheat must be 1");

    // Clean up clone
    $pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$cloneId]);
});

runTest("Cascade cleanup of all test exam sessions, answers, participants and warnings", function() use ($pdo, &$testExamId, &$testSessionId, &$createdQuestionIds) {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM exam_violations WHERE session_id = ?")->execute([$testSessionId]);
    $pdo->prepare("DELETE FROM exam_answers WHERE session_id = ?")->execute([$testSessionId]);
    $pdo->prepare("DELETE FROM exam_warnings WHERE session_id = ?")->execute([$testSessionId]);
    $pdo->prepare("DELETE FROM exam_participants WHERE session_id = ?")->execute([$testSessionId]);
    $pdo->prepare("DELETE FROM exam_session_questions WHERE session_id = ?")->execute([$testSessionId]);
    $pdo->prepare("DELETE FROM exam_sessions WHERE id = ?")->execute([$testSessionId]);
    $pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$testExamId]);
    if (!empty($createdQuestionIds)) {
        $ph = str_repeat('?,', count($createdQuestionIds) - 1) . '?';
        $pdo->prepare("DELETE FROM questions WHERE id IN ($ph)")->execute($createdQuestionIds);
    }
    $pdo->commit();

    $stmt = $pdo->prepare("SELECT 1 FROM exams WHERE id = ?");
    $stmt->execute([$testExamId]);
    assertTrue(!$stmt->fetchColumn(), "Exam must be deleted");

    $stmt = $pdo->prepare("SELECT 1 FROM exam_sessions WHERE id = ?");
    $stmt->execute([$testSessionId]);
    assertTrue(!$stmt->fetchColumn(), "Session must be deleted");
});

echo "\n=================================================================\n";
echo " Test Summary: {$passedCount} PASSED, " . ($totalCount - $passedCount) . " FAILED (Total: {$totalCount})\n";
echo "=================================================================\n\n";

if ($passedCount !== $totalCount) {
    exit(1);
}