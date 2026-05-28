<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();

$userId = (int)$_SESSION['user_id'];
$code = $_GET['code'] ?? 'INF.02';
$info = getQualificationInfo($code);
$stats = getCategoryStats($pdo, $userId);
$cat = $stats[$info['title']] ?? ['total' => 0, 'seen' => 0, 'correct' => 0, 'mastered' => 0];
$seenPercent = $cat['total'] > 0 ? round(($cat['seen'] / $cat['total']) * 100) : 0;
$correctPercent = $cat['total'] > 0 ? round(($cat['correct'] / $cat['total']) * 100) : 0;
$details = [
    'INF.02' => [
        'tasks' => ['montaż i diagnostyka stanowiska', 'adresacja IPv4 i test łączności', 'konfiguracja kont, grup i udziałów', 'urządzenia peryferyjne i dokumentacja'],
        'check' => ['sprawdź IP, bramę i DNS', 'przetestuj konto zwykłego użytkownika', 'zrób zrzuty po konfiguracji', 'opisz wykrytą usterkę'],
        'pitfalls' => ['pomylenie uprawnień NTFS i udostępniania', 'brak testu po restarcie', 'niespójna nazwa komputera lub grupy roboczej']
    ],
    'INF.03' => [
        'tasks' => ['import bazy i zapytania SQL', 'layout HTML/CSS zgodny z makietą', 'formularze i walidacja', 'PHP z bazą i wyświetlanie danych'],
        'check' => ['uruchom zapytania w phpMyAdmin', 'sprawdź puste formularze', 'zweryfikuj polskie znaki', 'odśwież stronę po zapisie'],
        'pitfalls' => ['literówki w nazwach tabel', 'brak htmlspecialchars przy wypisywaniu', 'niedopasowane kolory i wymiary z arkusza']
    ],
    'INF.04' => [
        'tasks' => ['algorytm i struktury danych', 'interfejs aplikacji', 'testy przypadków granicznych', 'opis techniczny rozwiązania'],
        'check' => ['uruchom minimalny scenariusz', 'sprawdź błędne dane wejściowe', 'zapisz wyniki testów', 'uporządkuj pliki projektu'],
        'pitfalls' => ['brak obsługi wyjątków', 'nieczytelne nazwy zmiennych', 'brak testu końcowego']
    ],
    'INF.07' => [
        'tasks' => ['VLAN i porty switcha', 'routing między sieciami', 'DHCP/DNS i usługi domenowe', 'reguły zapory i dokumentacja'],
        'check' => ['ping między VLAN', 'ipconfig /renew na kliencie', 'sprawdzenie DNS nazwą', 'zrzuty konfiguracji urządzeń'],
        'pitfalls' => ['zły port trunk/access', 'brak helper-address', 'nieautoryzowany DHCP lub zły DNS']
    ],
    'INF.08' => [
        'tasks' => ['diagnostyka sprzętu', 'instalacja dual boot', 'sterowniki drukarki/skanera', 'backup i harmonogram'],
        'check' => ['SMART lub test RAM', 'menu GRUB po restarcie', 'strona testowa drukarki', 'próba odtworzenia backupu'],
        'pitfalls' => ['zła kolejność instalacji systemów', 'brak sterownika producenta', 'skrypt backupu bez uprawnień wykonania']
    ],
];
$deepInfo = $details[$info['title']] ?? ['tasks' => [], 'check' => [], 'pitfalls' => []];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($info['title']); ?> - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <script src="assets/js/theme-handler.js"></script>
    <style>
        .qualification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(59, 130, 246, 0.14);
            color: #1d4ed8;
            border: 1px solid rgba(59, 130, 246, 0.25);
            border-radius: 999px;
            padding: 0.55rem 1rem;
            font-size: 0.9rem;
            font-weight: 700;
        }
        body.dark-mode .qualification-badge {
            background: rgba(96, 165, 250, 0.14);
            color: #bfdbfe;
            border-color: rgba(96, 165, 250, 0.3);
        }
        .qualification-panel h5 {
            margin-bottom: 1rem;
        }
        .qualification-panel p,
        .qualification-panel ul {
            font-size: 0.96rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include 'includes/topbar.php'; ?>
        <main role="main" class="content-body">
            <div class="container-fluid p-0">
                <div class="dashboard-panel mb-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <h2 class="fw-bold mb-2"><?php echo htmlspecialchars($info['title']); ?></h2>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($info['description']); ?></p>
                        </div>
                        <a href="test.php?mode=practice&category=<?php echo urlencode($info['title']); ?>&start=1" class="btn btn-primary rounded-pill px-4"><i class="bi bi-play-fill me-1"></i>Trenuj</a>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="dashboard-panel mb-4 qualification-panel">
                            <h5 class="fw-bold">Czego można się nauczyć</h5>
                            <p><?php echo htmlspecialchars($info['learn']); ?></p>
                            <h5 class="fw-bold mt-4">Technologie</h5>
                            <div class="d-flex flex-wrap gap-2"><?php foreach ($info['tech'] as $t): ?><span class="qualification-badge"><?php echo htmlspecialchars($t); ?></span><?php endforeach; ?></div>
                        </div>
                        <div class="dashboard-panel mb-4">
                            <div class="row g-4">
                                <div class="col-md-4"><h6 class="fw-bold">Typowe zadania</h6><ul class="text-muted mb-0"><?php foreach ($deepInfo['tasks'] as $v): ?><li><?php echo htmlspecialchars($v); ?></li><?php endforeach; ?></ul></div>
                                <div class="col-md-4"><h6 class="fw-bold">Checklista</h6><ul class="text-muted mb-0"><?php foreach ($deepInfo['check'] as $v): ?><li><?php echo htmlspecialchars($v); ?></li><?php endforeach; ?></ul></div>
                                <div class="col-md-4"><h6 class="fw-bold">Częste błędy</h6><ul class="text-muted mb-0"><?php foreach ($deepInfo['pitfalls'] as $v): ?><li><?php echo htmlspecialchars($v); ?></li><?php endforeach; ?></ul></div>
                            </div>
                        </div>
                        <div class="dashboard-panel">
                            <div class="row g-4">
                                <div class="col-md-6"><h6 class="fw-bold">Przykładowe stanowiska</h6><ul><?php foreach ($info['jobs'] as $v): ?><li><?php echo htmlspecialchars($v); ?></li><?php endforeach; ?></ul></div>
                                <div class="col-md-6"><h6 class="fw-bold">Ścieżki kariery</h6><ul><?php foreach ($info['paths'] as $v): ?><li><?php echo htmlspecialchars($v); ?></li><?php endforeach; ?></ul></div>
                            </div>
                            <h6 class="fw-bold mt-3">Możliwe zarobki</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($info['salary']); ?></p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="dashboard-panel mb-4">
                            <h5 class="fw-bold">Twoje statystyki</h5>
                            <div class="mb-3"><div class="d-flex justify-content-between small"><span>Poznane</span><strong><?php echo $seenPercent; ?>%</strong></div><div class="progress"><div class="progress-bar" style="width:<?php echo $seenPercent; ?>%"></div></div></div>
                            <div class="mb-3"><div class="d-flex justify-content-between small"><span>Poprawne</span><strong><?php echo $correctPercent; ?>%</strong></div><div class="progress"><div class="progress-bar bg-success" style="width:<?php echo $correctPercent; ?>%"></div></div></div>
                            <div class="small text-muted"><?php echo (int)$cat['seen']; ?> poznanych, <?php echo (int)$cat['correct']; ?> poprawnych, <?php echo (int)$cat['total']; ?> wszystkich pytań.</div>
                        </div>
                        <div class="dashboard-panel">
                            <h5 class="fw-bold">Powiązane kwalifikacje</h5>
                            <?php foreach ($info['related'] as $related): ?><a class="btn btn-sm btn-outline-primary rounded-pill me-1 mb-1" href="qualification.php?code=<?php echo urlencode($related); ?>"><?php echo htmlspecialchars($related); ?></a><?php endforeach; ?>
                            <?php if (empty($info['related'])): ?><p class="text-muted small mb-0">Brak danych.</p><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
