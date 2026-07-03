<?php
require_once __DIR__ . '/../includes/session.php';
startSecureSession();
require_once __DIR__ . '/../includes/KappiCrypt.php';

header('Content-Type: application/json');

$pubKey = KappiCrypt::getPublicKey();

echo json_encode([
    'status' => 'success',
    'publicKey' => $pubKey
]);
