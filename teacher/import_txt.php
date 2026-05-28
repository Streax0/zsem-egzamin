<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'dyrektor'])) {
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['questions_file'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('create_exam.php');
    }

    $file = $_FILES['questions_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        setSessionMessage('error', 'Błąd podczas przesyłania pliku.');
        redirect('create_exam.php');
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($extension, ['txt', 'csv'], true) || ($file['size'] ?? 0) > 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) {
        setSessionMessage('error', 'Nieprawidłowy plik. Dozwolone są pliki TXT/CSV do 1 MB.');
        redirect('create_exam.php');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        setSessionMessage('error', 'Nie można otworzyć pliku.');
        redirect('create_exam.php');
    }

    $importCount = 0;
    $errors = 0;
    $importedIds = [];
    $teacherName = $_SESSION['username'] ?? 'Nauczyciel';
    $importCategory = "Import_" . preg_replace('/[^a-zA-Z0-9]/', '', $teacherName) . "_" . date('Ymd');

    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue; // Skip empty lines and comments

        $parts = explode(';', $line);
        if (count($parts) >= 7) {
            // New format: category;question;a;b;c;d;correct;[image];[explanation]
            $data = [
                'category' => !empty($parts[0]) ? trim($parts[0]) : $importCategory,
                'question_text' => trim($parts[1]),
                'option_a' => trim($parts[2]),
                'option_b' => trim($parts[3]),
                'option_c' => trim($parts[4]),
                'option_d' => trim($parts[5]),
                'correct_answer' => strtoupper(trim($parts[6])),
                'image_url' => isset($parts[7]) ? trim($parts[7]) : '',
                'explanation' => isset($parts[8]) ? trim($parts[8]) : ''
            ];

            if (addQuestion($pdo, $data)) {
                $importCount++;
            } else {
                $errors++;
            }
        } else if (count($parts) === 6) {
            // Legacy format: question;a;b;c;d;correct
            $data = [
                'category' => $importCategory,
                'question_text' => trim($parts[0]),
                'option_a' => trim($parts[1]),
                'option_b' => trim($parts[2]),
                'option_c' => trim($parts[3]),
                'option_d' => trim($parts[4]),
                'correct_answer' => strtoupper(trim($parts[5])),
                'image_url' => '',
                'explanation' => ''
            ];

            if (addQuestion($pdo, $data)) {
                $importCount++;
            } else {
                $errors++;
            }
        } else {
            $errors++;
        }
    }
    fclose($handle);

    if ($importCount > 0) {
        setSessionMessage('success', "Zaimportowano $importCount pytań do kategorii: $importCategory. " . ($errors > 0 ? "Błędnych linii: $errors." : ""));
        // We could potentially redirect to create_exam with these questions pre-selected
        // For now just stay on create_exam
    } else {
        setSessionMessage('error', 'Nie udało się zaimportować żadnych pytań. Sprawdź format pliku.');
    }
    redirect('create_exam.php');
} else {
    redirect('create_exam.php');
}
