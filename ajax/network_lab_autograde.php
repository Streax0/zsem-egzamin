<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    securitySendJson(['success' => false, 'message' => 'Tylko zapytania POST są obsługiwane.'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$examRubricId = trim((string)($data['rubric_id'] ?? 'inf02_2024_06'));
$configState = is_array($data['config'] ?? null) ? $data['config'] : [];

$rubrics = [
    'inf02_2024_06' => [
        'name' => 'INF.02 Czerwiec 2024 (Zadanie 1: Konfiguracja Routera i DHCP)',
        'max_points' => 25,
        'criteria' => [
            [
                'id' => 'lan_ip',
                'name' => 'Adres IP interfejsu LAN Routera',
                'points' => 4,
                'check' => fn(array $cfg): bool => in_array($cfg['router_lan_ip'] ?? '', ['192.168.10.1', '192.168.10.254', '10.0.0.1'], true),
                'hint' => 'Router LAN IP powinien być pierwszym lub ostatnim adresem podsieci (np. 192.168.10.1).',
            ],
            [
                'id' => 'lan_mask',
                'name' => 'Maska podsieci LAN (/24 lub /26)',
                'points' => 3,
                'check' => fn(array $cfg): bool => in_array($cfg['router_lan_mask'] ?? '', ['255.255.255.0', '255.255.255.192', '24', '26'], true),
                'hint' => 'Ustaw maskę podsieci na 255.255.255.0 (/24).',
            ],
            [
                'id' => 'dhcp_enabled',
                'name' => 'Aktywacja serwera DHCP',
                'points' => 4,
                'check' => fn(array $cfg): bool => !empty($cfg['dhcp_enabled']),
                'hint' => 'Włącz usługę DHCP Server na interfejsie LAN.',
            ],
            [
                'id' => 'dhcp_pool_range',
                'name' => 'Zakres puli adresów DHCP',
                'points' => 5,
                'check' => function(array $cfg): bool {
                    $start = (string)($cfg['dhcp_start'] ?? '');
                    $end = (string)($cfg['dhcp_end'] ?? '');
                    return $start !== '' && $end !== '' && $start !== $end;
                },
                'hint' => 'Zdefiniuj poprawny zakres początkowy i końcowy puli DHCP.',
            ],
            [
                'id' => 'dns_server',
                'name' => 'Konfiguracja serwera DNS',
                'points' => 4,
                'check' => fn(array $cfg): bool => in_array($cfg['dns_primary'] ?? '', ['192.168.10.1', '8.8.8.8', '1.1.1.1', '8.8.4.4'], true),
                'hint' => 'Wskaż adres serwera DNS (np. 192.168.10.1 lub 8.8.8.8).',
            ],
            [
                'id' => 'wifi_security',
                'name' => 'Zabezpieczenie sieci bezprzewodowej (WPA2/WPA3)',
                'points' => 5,
                'check' => fn(array $cfg): bool => in_array(strtolower((string)($cfg['wifi_security'] ?? '')), ['wpa2', 'wpa2-psk', 'wpa3', 'wpa2/wpa3'], true) && mb_strlen((string)($cfg['wifi_key'] ?? '')) >= 8,
                'hint' => 'Ustaw szyfrowanie WPA2-PSK z hasłem liczącym minimum 8 znaków.',
            ],
        ],
    ],
    'inf02_2023_06' => [
        'name' => 'INF.02 Czerwiec 2023 (Konfiguracja Przełącznika VLAN)',
        'max_points' => 20,
        'criteria' => [
            [
                'id' => 'vlan_10',
                'name' => 'Utworzenie VLAN 10 (Zarządzanie)',
                'points' => 5,
                'check' => fn(array $cfg): bool => !empty($cfg['vlan_10_created']),
                'hint' => 'Stwórz VLAN o ID 10 i nazwie Zarzadzanie.',
            ],
            [
                'id' => 'vlan_20',
                'name' => 'Utworzenie VLAN 20 (Pracownicy)',
                'points' => 5,
                'check' => fn(array $cfg): bool => !empty($cfg['vlan_20_created']),
                'hint' => 'Stwórz VLAN o ID 20 dla ruchu pracowników.',
            ],
            [
                'id' => 'trunk_port',
                'name' => 'Konfiguracja portu magistrali (Trunk)',
                'points' => 5,
                'check' => fn(array $cfg): bool => in_array($cfg['trunk_port'] ?? '', ['GigabitEthernet0/1', 'eth1', 'port1', 'Gi0/1'], true),
                'hint' => 'Ustaw port łączący ze switchem nadrzędnym w trybie Trunk (802.1Q).',
            ],
            [
                'id' => 'management_ip',
                'name' => 'Adres IP interfejsu SVI VLAN 1',
                'points' => 5,
                'check' => fn(array $cfg): bool => !empty($cfg['svi_ip']),
                'hint' => 'Przypisz adres IP dla interfejsu wirtualnego przełącznika (interface vlan 1/10).',
            ],
        ],
    ],
];

$selectedRubric = $rubrics[$examRubricId] ?? $rubrics['inf02_2024_06'];
$totalEarned = 0;
$maxPoints = (int)$selectedRubric['max_points'];
$results = [];

foreach ($selectedRubric['criteria'] as $crit) {
    $passed = false;
    try {
        $passed = (bool)$crit['check']($configState);
    } catch (Throwable $e) {
        $passed = false;
    }

    $earned = $passed ? (int)$crit['points'] : 0;
    $totalEarned += $earned;

    $results[] = [
        'id' => $crit['id'],
        'name' => $crit['name'],
        'points_max' => (int)$crit['points'],
        'points_earned' => $earned,
        'passed' => $passed,
        'hint' => $passed ? 'Spełniono kryterium oceny CKE.' : $crit['hint'],
    ];
}

$scorePercent = $maxPoints > 0 ? (int)round(($totalEarned / $maxPoints) * 100) : 0;
$passedExam = $scorePercent >= 50;

$currentUser = getCurrentUser();
if ($currentUser && $passedExam) {
    try {
        awardUserXp((int)$currentUser['id'], 20, "Auto-Grader CKE: " . $selectedRubric['name']);
    } catch (Throwable $e) {}
}

securitySendJson([
    'success' => true,
    'rubric_name' => $selectedRubric['name'],
    'score_percent' => $scorePercent,
    'total_earned' => $totalEarned,
    'max_points' => $maxPoints,
    'passed' => $passedExam,
    'criteria' => $results,
    'verdict' => $passedExam ? 'EGZAMIN ZDANY (>=50%)' : 'EGZAMIN NIEZALICZONY (<50%)',
], 200);
