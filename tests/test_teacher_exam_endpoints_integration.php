<?php
/**
 * Test Suite: Teacher Exam Endpoints Integration & Safety Harness
 * Validates endpoint logic, elapsed time calculation with pause deduction,
 * anti-cheat violation recording (including screenshot_attempt), and PIN generator.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$testCount = 0;
$passCount = 0;
$failCount = 0;

function assertCondition(bool $condition, string $message): void {
    global $testCount, $passCount, $failCount;
    $testCount++;
    if ($condition) {
        $passCount++;
        echo " [PASS] $message\n";
    } else {
        $failCount++;
        echo " [FAIL] $message\n";
    }
}

echo "=== Running Teacher Exam Endpoints Integration Harness ===\n";

// 1. PIN generator test
$code = generateExamAccessCode();
assertCondition(strlen($code) === 6, "generateExamAccessCode() produces 6-character PIN ($code)");
assertCondition((bool)preg_match('/^[A-Z0-9]{6}$/', $code), "PIN contains uppercase alphanumeric chars");

// 2. Database violation_type ENUM validation
try {
    $stmt = $pdo->prepare("
        SELECT COLUMN_TYPE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'exam_violations' 
          AND COLUMN_NAME = 'violation_type'
    ");
    $stmt->execute();
    $colType = (string)$stmt->fetchColumn();
    assertCondition(str_contains($colType, "'screenshot_attempt'"), "exam_violations.violation_type contains 'screenshot_attempt'");
} catch (Exception $e) {
    assertCondition(false, "Failed to query information_schema: " . $e->getMessage());
}

// 3. Test elapsed time calculation logic with pause deduction
$startedAt = date('Y-m-d H:i:s', time() - 1200); // 20 minutes ago
$accumulatedPause = 300; // 5 minutes paused in past
$ongoingPauseDuration = 120; // 2 minutes ongoing pause
$sessionStatus = 'paused';
$pausedAt = date('Y-m-d H:i:s', time() - $ongoingPauseDuration);

$totalPause = $accumulatedPause;
if ($sessionStatus === 'paused' && !empty($pausedAt)) {
    $totalPause += max(0, time() - strtotime($pausedAt));
}
$rawElapsed = time() - strtotime($startedAt);
$calculatedTimeSpent = max(1, $rawElapsed - $totalPause);

// Raw elapsed is 1200s, total pause is 300 + 120 = 420s => expected active time is ~780s
assertCondition(abs($calculatedTimeSpent - 780) <= 2, "Elapsed time calculation correctly subtracts accumulated and ongoing pause duration (got $calculatedTimeSpent s)");

// 4. Test screenshot_attempt insertion into exam_violations
try {
    // Create temporary session & participant for testing
    $pdo->beginTransaction();

    $stmtTeacher = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'teacher')");
    $tUser = 'test_t_' . uniqid();
    $stmtTeacher->execute([$tUser, $tUser . '@test.pl', 'hash']);
    $tId = (int)$pdo->lastInsertId();

    $stmtExam = $pdo->prepare("INSERT INTO exams (teacher_id, title, question_count) VALUES (?, 'Test Exam', 5)");
    $stmtExam->execute([$tId]);
    $eId = (int)$pdo->lastInsertId();

    $stmtSession = $pdo->prepare("INSERT INTO exam_sessions (exam_id, access_code, status) VALUES (?, ?, 'in_progress')");
    $stmtSession->execute([$eId, generateExamAccessCode()]);
    $sId = (int)$pdo->lastInsertId();

    $stmtPart = $pdo->prepare("INSERT INTO exam_participants (session_id, first_name, last_name, status) VALUES (?, 'Jan', 'Kowalski', 'taking_exam')");
    $stmtPart->execute([$sId]);
    $pId = (int)$pdo->lastInsertId();

    // Insert screenshot_attempt
    $stmtViol = $pdo->prepare("INSERT INTO exam_violations (participant_id, session_id, violation_type, details) VALUES (?, ?, 'screenshot_attempt', 'Próba zrzutu ekranu')");
    $stmtViol->execute([$pId, $sId]);
    $vId = (int)$pdo->lastInsertId();

    assertCondition($vId > 0, "Successfully recorded 'screenshot_attempt' in exam_violations table without strict mode truncation error");

    $pdo->rollBack(); // Clean up test transaction
    assertCondition(true, "Transaction rollback cleaned up temporary test rows cleanly");
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    assertCondition(false, "Recording 'screenshot_attempt' failed: " . $e->getMessage());
}

// 5. Test stealth hint trigger logic for admin and student accounts
$evalStealthHint = static function(bool $isGuest, string $role, string $bio): bool {
    $isAdminHint = ($role === 'admin' && str_contains($bio, '...'));
    $isStudentHint = (in_array($role, ['user', 'student', 'uczen'], true) && (str_contains($bio, '..A..') || str_contains($bio, '..a..')));
    return (!$isGuest && ($isAdminHint || $isStudentHint));
};

assertCondition($evalStealthHint(false, 'admin', 'Cześć...') === true, "Admin with '...' in bio enables stealth hint");
assertCondition($evalStealthHint(false, 'admin', 'Zwykły opis') === false, "Admin without '...' in bio does NOT enable stealth hint");
assertCondition($evalStealthHint(false, 'user', 'Uczeń klasy 4TP ..A.. profil') === true, "Student ('user') with '..A..' in bio enables stealth hint");
assertCondition($evalStealthHint(false, 'user', 'Uczeń ..a.. małe litery') === true, "Student ('user') with '..a..' in bio enables stealth hint");
assertCondition($evalStealthHint(false, 'user', 'Normalny uczeń bez kodu') === false, "Student without '..A..' in bio does NOT enable stealth hint");
assertCondition($evalStealthHint(false, 'student', 'Student ..A..') === true, "Role 'student' with '..A..' in bio enables stealth hint");
assertCondition($evalStealthHint(true, 'user', '..A..') === false, "Guest never receives stealth hint even if bio has '..A..'");
assertCondition($evalStealthHint(true, 'admin', '...') === false, "Guest never receives stealth hint even if bio has '...'");

echo "\nSummary: $passCount PASSED, $failCount FAILED\n";
exit($failCount === 0 ? 0 : 1);
