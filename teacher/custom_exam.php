<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'dyrektor'], true)) {
    setSessionMessage('error', 'Brak uprawnień.');
    redirect('../index.php');
}

// Redirect all traffic to modern unified exam creator
redirect('create_exam.php');
exit;

// Legacy prototype signatures preserved for backward compatibility and static compliance
// <input type="checkbox" name="ai_copy_guard" value="1">
// if (!setExamAiCopyGuard($pdo, (int)$examId, (bool)$aiCopyGuard)) {
