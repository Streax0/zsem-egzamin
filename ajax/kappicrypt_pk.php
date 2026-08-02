<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
securityApplyJsonHeaders();
require_once __DIR__ . '/../includes/KappiCrypt.php';

$pubKey = KappiCrypt::getPublicKey();

echo securityJsonEncode([
    'status' => 'success',
    'publicKey' => $pubKey
]);
