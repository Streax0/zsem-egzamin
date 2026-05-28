<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();

$userId = $_SESSION['user_id'];
$catStats = getCategoryStats($pdo, $userId);
ksort($catStats); // Sort by category name

// Quick stats for header
$totalQuestions = array_sum(array_column($catStats, 'total'));
$totalMastered = array_sum(array_column($catStats, 'mastered'));
$overallPercent = $totalQuestions > 0 ? round(($totalMastered / $totalQuestions) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategorie pytań – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <script src="assets/js/theme-handler.js"></script>
    <style>
        .category-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }
        .category-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%);
            color: var(--primary-color);
            transition: all 0.3s;
        }
        .category-card:hover .category-icon {
            background: var(--primary-color);
            color: white;
            transform: scale(1.1);
        }
        .progress {
            background-color: rgba(0,0,0,0.05);
            border-radius: 10px;
        }
        body.dark-mode .progress {
            background-color: rgba(255,255,255,0.05);
        }
        .search-container {
            position: relative;
        }
        .search-container i {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        .search-container input {
            padding-left: 3rem;
            border-radius: 100px;
            border: 1px solid var(--border-color);
            background-color: var(--panel-bg);
            color: var(--text-main);
            transition: all 0.2s;
        }
        .search-container input:focus {
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            border-color: var(--primary-color);
            outline: none;
        }
        .category-info-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 1rem;
        }
        .category-info-box {
            border-radius: 18px;
            padding: 1rem;
            background: #f8fafc;
            border: 1px solid rgba(148,163,184,.2);
        }
        body.dark-mode .category-info-box { background: #0f172a; border-color: rgba(148,163,184,.25); }
        @media (max-width: 767.98px) { .category-info-strip { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include 'includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">
                    
                    <!-- Header -->
                    <div class="row align-items-end mb-4 animate-in">
                        <div class="col-md-7">
                            <h2 class="fw-bold mb-1">Kategorie pytań</h2>
                            <p class="text-muted mb-0">Wybierz kategorię, aby rozpocząć ukierunkowany trening i opanować konkretny materiał.</p>
                        </div>
                        <div class="col-md-5 mt-3 mt-md-0">
                            <div class="search-container">
                                <i class="bi bi-search"></i>
                                <input type="text" id="categorySearch" class="form-control form-control-lg" placeholder="Szukaj kategorii...">
                            </div>
                        </div>
                    </div>

                    <!-- Overall Progress Card -->
                    <div class="dashboard-panel mb-4 animate-in" style="animation-delay: 0.1s;">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="fw-bold mb-2">Twój ogólny postęp</h5>
                                <p class="text-muted small mb-3">Opanowałeś <strong><?= $totalMastered ?></strong> z <strong><?= $totalQuestions ?></strong> wszystkich pytań w systemie.</p>
                                <div class="progress" style="height: 12px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $overallPercent ?>%" aria-valuenow="<?= $overallPercent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <div class="h1 fw-800 text-primary mb-0"><?= $overallPercent ?>%</div>
                                <div class="text-muted small">Opanowania bazy</div>
                            </div>
                        </div>
                        <div class="category-info-strip mt-4">
                            <div class="category-info-box"><strong><i class="bi bi-info-circle text-primary me-2"></i>Opis kwalifikacji</strong><div class="small text-muted mt-1">Każdy kafelek prowadzi do strony z zakresem, stanowiskami, technologiami i ścieżkami kariery.</div></div>
                            <div class="category-info-box"><strong><i class="bi bi-tools text-success me-2"></i>Praktyka</strong><div class="small text-muted mt-1">Nowa zakładka Praktyka wyjaśnia arkusze, punktację i przebieg egzaminu zawodowego.</div></div>
                            <div class="category-info-box"><strong><i class="bi bi-graph-up text-warning me-2"></i>Statystyki</strong><div class="small text-muted mt-1">Procent liczy opanowane pytania względem całej bazy danej kwalifikacji.</div></div>
                        </div>
                    </div>

                    <!-- Categories Grid -->
                    <div class="row g-4" id="categoriesGrid">
                        <?php if (empty($catStats)): ?>
                            <div class="col-12">
                                <div class="dashboard-panel text-center py-5">
                                    <i class="bi bi-folder-x display-1 text-muted opacity-25 mb-3 d-block"></i>
                                    <h4 class="text-muted">Brak kategorii</h4>
                                    <p class="text-muted">Nie znaleziono żadnych pytań w bazie danych.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php 
                            $delay = 0.2;
                            foreach ($catStats as $name => $s): 
                                $percent = $s['total'] > 0 ? round(($s['mastered'] / $s['total']) * 100) : 0;
                            ?>
                                <div class="col-md-6 col-lg-4 category-item" data-name="<?= strtolower($name) ?>">
                                    <div class="dashboard-panel h-100 category-card animate-in" style="animation-delay: <?= $delay ?>s;">
                                        <div class="category-icon">
                                            <i class="bi bi-folder2-open"></i>
                                        </div>
                                        <h4 class="fw-bold mb-1"><?= htmlspecialchars($name) ?></h4>
                                        <div class="d-flex justify-content-between mb-3">
                                            <span class="text-muted small"><?= $s['total'] ?> pytań</span>
                                            <span class="fw-bold small"><?= $percent ?>%</span>
                                        </div>
                                        
                                        <div class="progress mb-4" style="height: 6px;">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $percent ?>%"></div>
                                        </div>

                                        <div class="mt-auto">
                                            <a href="qualification.php?code=<?= urlencode($name) ?>" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">
                                                <i class="bi bi-box-arrow-up-right me-1"></i>Zobacz kwalifikację
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                $delay += 0.05;
                            endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Simple category search
        document.getElementById('categorySearch').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.category-item');
            
            items.forEach(item => {
                const name = item.getAttribute('data-name');
                if (name.includes(term)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
