<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kariery - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <script src="assets/js/theme-handler.js"></script>
</head>
<body>
<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include 'includes/topbar.php'; ?>
        <main class="content-body">
            <div class="container-fluid p-0">
                <section class="dashboard-panel mb-4 p-4 p-lg-5" style="background:linear-gradient(135deg,var(--primary-color-dark),#0f172a);color:#fff;border-radius:28px;">
                    <span class="badge bg-white bg-opacity-25 rounded-pill mb-3">Dołącz do projektu</span>
                    <h1 class="fw-800 mb-3"; style="color: #fff;">Rozwijaj ZSEM Tech razem z nami</h1>
                    <p class="lead mb-4" style="max-width:760px;">Szukamy osób, które chcą pomagać przy kodzie, UI, testach, treściach i aktualizacjach platformy.</p>
                    <a href="mailto:zsemtech@zsem.edu.pl?subject=Chcę%20dołączyć%20do%20ZSEM%20Tech" class="btn btn-light btn-lg rounded-pill px-4 fw-bold">
                        <i class="bi bi-envelope me-2"></i>Zgłoś się
                    </a>
                </section>

                <div class="row g-4">
                    <?php
                    $roles = [
                        ['Programiści', 'bi-code-slash', 'PHP, JavaScript, SQL, poprawki błędów i nowe moduły.'],
                        ['Design i UI', 'bi-palette', 'Układy stron, responsywność, dostępność i spójność wizualna.'],
                        ['Testerzy', 'bi-bug', 'Sprawdzanie testów, sprawdzianów, profili i widoków platformy.'],
                        ['Treści', 'bi-journal-text', 'Opisy kwalifikacji, słownik pojęć, materiały pomocnicze i aktualizacje.'],
                    ];
                    foreach ($roles as [$title, $icon, $desc]):
                    ?>
                    <div class="col-md-6 col-xl-3">
                        <div class="dashboard-panel h-100">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary mb-3"><i class="bi <?php echo $icon; ?>"></i></div>
                            <h4 class="fw-bold"><?php echo $title; ?></h4>
                            <p class="text-muted mb-0"><?php echo $desc; ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-xl-5">
                        <div class="dashboard-panel h-100">
                            <h4 class="fw-bold mb-3"><i class="bi bi-diagram-3 text-primary me-2"></i>Struktura ZSEM Tech</h4>
                            <?php
                            $board = [
                                ['Michał Michalik', 'Przewodniczący, współtwórca strony', 'bi-person-badge'],
                                ['Paweł Madzia', 'Zastępca przewodniczącego', 'bi-person-check'],
                                ['Damian Podgórski', 'Zastępca przewodniczącego, współtwórca strony', 'bi-person-check'],
                                ['Amelia Sułkowska', 'Skarbnik', 'bi-cash-coin'],
                            ];
                            foreach ($board as [$name, $desc, $icon]):
                            ?>
                                <div class="d-flex gap-3 py-3 border-bottom">
                                    <div class="stat-icon bg-primary bg-opacity-10 text-primary flex-shrink-0"><i class="bi <?php echo $icon; ?>"></i></div>
                                    <div><div class="fw-bold"><?php echo $name; ?></div><div class="text-muted small"><?php echo $desc; ?></div></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-xl-7">
                        <div class="dashboard-panel h-100">
                            <h4 class="fw-bold mb-3"><i class="bi bi-list-check text-success me-2"></i>Podział obowiązków</h4>
                            <div class="row g-3">
                                <?php
                                $sections = [
                                    ['Programowanie', 'rozwój platformy, poprawki błędów, integracje, baza danych'],
                                    ['Media i fotografia', 'relacje z wydarzeń, grafiki, materiały promocyjne'],
                                    ['Elektronika i sprzęt', 'stanowiska, diagnostyka, overclocking, wsparcie techniczne'],
                                    ['Logistyka', 'organizacja wydarzeń, warsztatów, turniejów i pokazów'],
                                    ['Matematyka i nauka', 'materiały edukacyjne, zadania, przygotowanie do egzaminów'],
                                    ['E-sport', 'turnieje, regulaminy, obsługa techniczna rozgrywek'],
                                ];
                                foreach ($sections as [$name, $desc]):
                                ?>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded-4 border h-100">
                                            <div class="fw-bold"><?php echo $name; ?></div>
                                            <div class="text-muted small"><?php echo $desc; ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-panel mt-4">
                    <h4 class="fw-bold mb-3"><i class="bi bi-person-hearts text-primary me-2"></i>Opiekunowie ZSEM Tech</h4>
                    <p class="text-muted">Opiekunami klubu są Tomasz Mąka, Rafał Ciastoń oraz Anna Kochanek. Wspierają klub organizacyjnie, edukacyjnie i formalnie przy działaniach szkolnych.</p>
                    <div class="row g-3">
                        <?php foreach (['Tomasz Mąka', 'Rafał Ciastoń', 'Anna Kochanek'] as $mentor): ?>
                            <div class="col-md-4">
                                <div class="p-3 rounded-4 border h-100 d-flex align-items-center gap-3">
                                    <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-mortarboard"></i></div>
                                    <div><div class="fw-bold"><?php echo $mentor; ?></div><div class="small text-muted">Opiekun klubu</div></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="dashboard-panel mt-4">
                    <h4 class="fw-bold mb-3"><i class="bi bi-kanban text-success me-2"></i>Aktualne potrzeby projektu</h4>
                    <div class="row g-3">
                        <?php
                        $needs = [
                            ['Audyt dostępności', 'sprawdzanie klawiatury, kontrastu, etykiet formularzy i czytelności komunikatów'],
                            ['Baza pytań', 'weryfikacja pytań, usuwanie duplikatów, dopisywanie wyjaśnień i kategorii'],
                            ['UX testów', 'testowanie przebiegu testu, sprawdzianu, lobby i wyników'],
                            ['Bezpieczeństwo', 'zgłaszanie błędów logicznych, problemów z kontem i uprawnieniami'],
                        ];
                        foreach ($needs as [$name, $desc]):
                        ?>
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 border h-100">
                                    <strong><?php echo htmlspecialchars($name); ?></strong>
                                    <div class="small text-muted mt-1"><?php echo htmlspecialchars($desc); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="dashboard-panel mt-4">
                    <h4 class="fw-bold mb-3"><i class="bi bi-trophy text-warning me-2"></i>Największe osiągnięcia klubu</h4>
                    <div class="row g-3">
                        <?php
                        $achievements = [
                            'Wykłady podczas 20. i 21. Studenckiego Festiwalu Informatycznego na UJ.',
                            'Organizacja trzech edycji ogólnopolskich zawodów ZSEM OC CUP.',
                            'Realizacja medialna lokalnych zawodów Geoguessr w 2025 roku.',
                            'Organizacja debat kandydatów na przewodniczącego szkoły 2025.',
                            'Reprezentowanie szkoły na targach szkół w MOSiR 2024.',
                            'Wsparcie logistyczne Pikniku Naukowego ZSE-M oraz zajęcia INF.02.',
                        ];
                        foreach ($achievements as $achievement):
                        ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="p-3 rounded-4 bg-light border h-100">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i><?php echo $achievement; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="dashboard-panel mt-4">
                    <h4 class="fw-bold mb-3"><i class="bi bi-file-earmark-text text-primary me-2"></i>Regulamin w skrócie</h4>
                    <p class="text-muted">ZSEM Tech jest szkolnym klubem informatycznym działającym przy ZSE-M w Nowym Sączu. Klub rozwija kompetencje z informatyki, programowania, elektroniki, mediów, matematyki, e-sportu, overclockingu i logistyki wydarzeń.</p>
                    <div class="row g-3">
                        <div class="col-md-4"><div class="p-3 rounded-4 border h-100"><strong>Członkostwo</strong><div class="small text-muted">Dobrowolne, dla uczniów szkoły. Wymagana aktywność i przestrzeganie zasad.</div></div></div>
                        <div class="col-md-4"><div class="p-3 rounded-4 border h-100"><strong>Prawa</strong><div class="small text-muted">Udział w zajęciach, projektach, korzystanie z zasobów i zgłaszanie inicjatyw.</div></div></div>
                        <div class="col-md-4"><div class="p-3 rounded-4 border h-100"><strong>Obowiązki</strong><div class="small text-muted">Dbanie o dobre imię szkoły, sprzęt, projekty i zasady klubu.</div></div></div>
                    </div>
                    <div class="mt-4">
                        <h5 class="fw-bold">Jak się zgłosić?</h5>
                        <p class="text-muted mb-0">Napisz do zespołu ZSEM Tech albo zgłoś się do opiekuna projektu, p. Ciastonia. Podaj, czym chcesz się zajmować i jakie masz doświadczenie.</p>
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
