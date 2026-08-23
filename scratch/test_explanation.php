<?php
require_once __DIR__ . '/../includes/functions.php';

$question = [
    'question_text' => 'Który z podanych adresów IPv4 jest adresem prywatnym?',
    'option_a' => '8.8.8.8',
    'option_b' => '172.16.0.1',
    'option_c' => '1.1.1.1',
    'option_d' => '209.85.231.104',
    'correct_answer' => 'B',
    'category' => 'INF.02',
];

echo buildQuestionExplanation($question, 'A');
