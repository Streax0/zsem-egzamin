<?php
require_once __DIR__ . '/../includes/AiTutorEngine.php';

$resB = aiTutorAnalyzeTechnicalOption("190.5.7.126", "Który adres jest prywatny?", "10.0.0.1", false, "INF.02");
$resC = aiTutorAnalyzeTechnicalOption("131.107.5.65", "Który adres jest prywatny?", "10.0.0.1", false, "INF.02");
$resD = aiTutorAnalyzeTechnicalOption("38.176.55.44", "Który adres jest prywatny?", "10.0.0.1", false, "INF.02");
$resA = aiTutorAnalyzeTechnicalOption("10.0.0.1", "Który adres jest prywatny?", "10.0.0.1", true, "INF.02");

echo "A: " . $resA . "\n";
echo "B: " . $resB . "\n";
echo "C: " . $resC . "\n";
echo "D: " . $resD . "\n";
