<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['question_id'] = 1;
$_POST['user_answer'] = 'A';
require __DIR__ . '/../ajax/ai_tutor_explain.php';
