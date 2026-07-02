<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'dyrektor'], true)) {
    setSessionMessage('error', 'Brak uprawnień do pomocy nauczyciela.');
    redirect('../index.php');
}

$sections = [
    [
        'icon' => 'bi-plus-square',
        'title' => 'Testy online',
        'items' => [
            'Utwórz test online z puli pytań, konkretnych kategorii albo własnego sprawdzianu.',
            'Ustaw czas, limit uczestników, próg zaliczenia, losowanie pytań i odpowiedzi.',
            'Włącz tryb egzaminacyjny, blokadę zmiany kart, pełny ekran, poczekalnię i ponowne wejście.',
            'Określ, kiedy uczeń widzi wynik, ocenę przewidywaną i poprawne odpowiedzi.',
        ],
    ],
    [
        'icon' => 'bi-broadcast',
        'title' => 'Hostowanie sesji',
        'items' => [
            'Uruchom sesję, skopiuj kod dostępu albo pokaż kod QR uczniom.',
            'Śledź uczestników, postęp, naruszenia i aktywność w czasie rzeczywistym.',
            'Pauzuj, wznawiaj, kończ sesję albo usuń uczestnika, gdy test wymaga interwencji.',
        ],
    ],
    [
        'icon' => 'bi-file-earmark-pdf',
        'title' => 'Sprawdziany do druku',
        'items' => [
            'Generator PDF tworzy arkusze przeznaczone do wydruku: z bazy, TXT albo pytań wpisanych ręcznie.',
            'Możesz wydrukować arkusz, zapisać go jako PDF i przenieść podgląd do Moich sprawdzianów.',
            'Klucz odpowiedzi może zawierać wyjaśnienia, a branding ZSEM Tech jest dodawany automatycznie.',
        ],
    ],
    [
        'icon' => 'bi-folder-check',
        'title' => 'Moje sprawdziany',
        'items' => [
            'Zapisuj własne zestawy pytań jako sprawdziany online.',
            'Edytuj pytania, odpowiedzi, wyjaśnienia, obrazy, tagi i ustawienia publikacji.',
            'Na bazie zapisanego sprawdzianu możesz szybko utworzyć nowy test online.',
        ],
    ],
    [
        'icon' => 'bi-person-lines-fill',
        'title' => 'Wyniki i weryfikacja',
        'items' => [
            'Po sesji sprawdzisz szczegóły uczestników, odpowiedzi i procentowy wynik.',
            'Jeśli pytanie wymaga korekty, możesz zgłosić zmianę poprawnej odpowiedzi do administracji.',
            'Historia sesji pomaga porównać klasy, terminy i ustawienia testów.',
        ],
    ],
    [
        'icon' => 'bi-send',
        'title' => 'Wnioski do administracji',
        'items' => [
            'Wysyłaj prośby techniczne, zgłoszenia błędów i pytania o uprawnienia.',
            'Odpowiedzi administracji pojawiają się w panelu wniosków i w powiadomieniach.',
        ],
    ],
];

$pageTitle = 'Pomoc nauczyciela - ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
    <style>
        .teacher-help-shell { max-width: 1180px; margin: 0 auto; }
        .teacher-help-hero {
            border-radius: 8px;
            padding: clamp(1.25rem, 3vw, 2.25rem);
            color: #fff;
            background: linear-gradient(135deg, #1d4ed8, #0f172a);
            box-shadow: 0 20px 48px rgba(37, 99, 235, .16);
        }
        .help-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        .teacher-help-card { border-radius: 8px; border: 1px solid rgba(148,163,184,.22); background: var(--panel-bg); padding: 1.1rem; }
        .teacher-help-card i { font-size: 1.45rem; color: var(--primary-color); }
        .teacher-help-card li { margin-bottom: .45rem; }
    </style>
HTML;
include '../includes/header.php';
?>
<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include '../includes/topbar.php'; ?>
        <main class="content-body">
            <div class="teacher-help-shell">
                <section class="teacher-help-hero mb-4">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <span class="badge bg-white bg-opacity-25 rounded-pill mb-3">Tylko dla nauczycieli</span>
                            <h1 class="fw-900 mb-2">Pomoc panelu nauczyciela</h1>
                            <p class="mb-0">Szybka dokumentacja funkcji, które służą do tworzenia, prowadzenia i sprawdzania testów.</p>
                        </div>
                        <a href="index.php" class="btn btn-light rounded-pill px-4"><i class="bi bi-arrow-left me-1"></i>Panel</a>
                    </div>
                </section>

                <section class="teacher-help-grid">
                    <?php foreach ($sections as $section): ?>
                        <article class="teacher-help-card">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="bi <?= htmlspecialchars($section['icon']) ?>"></i>
                                <h2 class="h5 fw-bold mb-0"><?= htmlspecialchars($section['title']) ?></h2>
                            </div>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($section['items'] as $item): ?>
                                    <li><?= htmlspecialchars($item) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </section>
            </div>
        </main>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="../assets/js/theme-handler.js"></script>
</body>
</html>
