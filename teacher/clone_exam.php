<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'dyrektor'], true)) {
    http_response_code(403);
    die('Unauthorized');
}

$examId = (int)(($_POST['id'] ?? null) ?: ($_GET['id'] ?? 0));
$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->execute([$examId]);
$source = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$source) {
    setSessionMessage('error', 'Sprawdzian nie istnieje.');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $token = generateCsrfToken();
    ?>
    <!DOCTYPE html>
    <html lang="pl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Duplikuj sprawdzian</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
        <link rel="stylesheet" href="../assets/css/style.css">
        <link rel="stylesheet" href="../assets/css/dashboard-new.css">
    </head>
    <body>
        <main role="main" class="container py-5" style="max-width: 680px;">
            <div class="dashboard-panel">
                <h1 class="h4 fw-bold mb-3">Duplikuj sprawdzian</h1>
                <p class="text-muted">Utworzysz własną kopię sprawdzianu: <strong><?= htmlspecialchars($source['title'] ?? 'Bez tytułu') ?></strong>.</p>
                <form method="POST" class="d-flex gap-2 flex-wrap mt-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="id" value="<?= (int)$examId ?>">
                    <button type="submit" class="btn btn-primary">Utwórz kopię</button>
                    <a href="index.php" class="btn btn-outline-secondary">Anuluj</a>
                </form>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setSessionMessage('error', 'Nieprawidłowy token CSRF.');
    redirect('index.php');
}

try {
    $copyColumns = [
        'title',
        'description',
        'question_count',
        'selected_questions',
        'categories',
        'difficulty_level',
        'shuffle_questions',
        'shuffle_answers',
        'max_participants',
        'time_per_question',
        'total_time',
        'exam_mode',
        'auto_finish_on_time',
        'allow_rejoin',
        'anti_cheat_enabled',
        'block_tab_switch',
        'require_fullscreen',
        'lobby_enabled',
        'show_results_to_student',
        'show_predicted_grade',
        'grade_thresholds',
        'settings',
    ];

    $columns = ['teacher_id'];
    $placeholders = ['?'];
    $params = [$userId];

    foreach ($copyColumns as $column) {
        if (!array_key_exists($column, $source)) {
            continue;
        }
        $columns[] = $column;
        $placeholders[] = '?';
        $params[] = $column === 'title' ? (($source[$column] ?? 'Sprawdzian') . ' (Kopia)') : $source[$column];
    }

    $sql = 'INSERT INTO exams (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $newId = (int)$pdo->lastInsertId();

    try {
        $stmt = $pdo->prepare("SELECT question_id FROM exam_questions WHERE exam_id = ?");
        $stmt->execute([$examId]);
        $questions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($questions)) {
            $stmtInsert = $pdo->prepare("INSERT INTO exam_questions (exam_id, question_id) VALUES (?, ?)");
            foreach ($questions as $qId) {
                $stmtInsert->execute([$newId, $qId]);
            }
        }
    } catch (PDOException $e) {
        // Newer schema stores selected questions directly on exams.
    }

    setSessionMessage('success', 'Sprawdzian został skopiowany pomyślnie.');
    redirect('index.php');
} catch (PDOException $e) {
    error_log('Clone exam error: ' . $e->getMessage());
    setSessionMessage('error', 'Błąd podczas kopiowania sprawdzianu.');
    redirect('index.php');
}
