<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    securitySendJson(['success' => false, 'message' => 'Tylko zapytania POST są dozwolone.'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$action = (string)($data['action'] ?? 'export');

if ($action === 'export') {
    $topology = is_array($data['topology'] ?? null) ? $data['topology'] : [];
    
    $cleanDevices = [];
    foreach (($topology['devices'] ?? []) as $dev) {
        if (!is_array($dev)) continue;
        $cleanDevices[] = [
            'id' => substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($dev['id'] ?? uniqid('dev_'))), 0, 32),
            'type' => in_array($dev['type'] ?? '', ['router', 'switch', 'pc', 'server', 'access_point'], true) ? $dev['type'] : 'pc',
            'name' => mb_substr(strip_tags((string)($dev['name'] ?? 'Device')), 0, 64, 'UTF-8'),
            'ip' => filter_var($dev['ip'] ?? '', FILTER_VALIDATE_IP) ? $dev['ip'] : '',
            'mask' => mb_substr(strip_tags((string)($dev['mask'] ?? '255.255.255.0')), 0, 16, 'UTF-8'),
            'gateway' => filter_var($dev['gateway'] ?? '', FILTER_VALIDATE_IP) ? $dev['gateway'] : '',
            'x' => max(0, min(2000, (int)($dev['x'] ?? 50))),
            'y' => max(0, min(2000, (int)($dev['y'] ?? 50))),
        ];
    }

    $cleanLinks = [];
    foreach (($topology['links'] ?? []) as $link) {
        if (!is_array($link)) continue;
        $cleanLinks[] = [
            'from' => substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($link['from'] ?? '')), 0, 32),
            'to' => substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($link['to'] ?? '')), 0, 32),
            'type' => in_array($link['type'] ?? '', ['copper', 'fiber', 'serial', 'wireless'], true) ? $link['type'] : 'copper',
        ];
    }

    $exportPayload = [
        'format' => 'zsem_topology_v1',
        'exported_at' => date('c'),
        'device_count' => count($cleanDevices),
        'devices' => $cleanDevices,
        'links' => $cleanLinks,
    ];

    securitySendJson([
        'success' => true,
        'topology' => $exportPayload,
        'json_string' => json_encode($exportPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    ], 200);
} elseif ($action === 'import') {
    $rawImport = (string)($data['json_data'] ?? '');
    try {
        $parsed = json_decode($rawImport, true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        securitySendJson(['success' => false, 'message' => 'Niepoprawny format JSON topologii.'], 422);
    }

    if (!is_array($parsed) || !isset($parsed['devices']) || !is_array($parsed['devices'])) {
        securitySendJson(['success' => false, 'message' => 'Struktura pliku nie zawiera listy urządzeń.'], 422);
    }

    securitySendJson([
        'success' => true,
        'message' => 'Topologia została pomyślnie wczytana (' . count($parsed['devices']) . ' urządzeń).',
        'topology' => $parsed,
    ], 200);
}

securitySendJson(['success' => false, 'message' => 'Nieznana akcja.'], 400);
