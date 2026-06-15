<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$missionGroups = [
    'daily' => syncUserMissionsForPeriod($pdo, $userId, 'daily', 3),
    'weekly' => syncUserMissionsForPeriod($pdo, $userId, 'weekly', 3),
    'monthly' => syncUserMissionsForPeriod($pdo, $userId, 'monthly', 3),
];
$missionMeta = [
    'daily' => ['title' => 'Misje dzienne', 'subtitle' => 'Odświeżają się codziennie o północy.', 'icon' => 'bi-sunrise', 'color' => 'primary', 'reset' => new DateTime('tomorrow midnight')],
    'weekly' => ['title' => 'Misje tygodniowe', 'subtitle' => 'Nowy zestaw w każdy poniedziałek.', 'icon' => 'bi-calendar-week', 'color' => 'success', 'reset' => (new DateTime('monday next week'))->setTime(0, 0)],
    'monthly' => ['title' => 'Misje miesięczne', 'subtitle' => 'Reset każdego 1. dnia miesiąca.', 'icon' => 'bi-calendar3', 'color' => 'warning', 'reset' => (new DateTime('first day of next month'))->setTime(0, 0)],
];
$allMissions = [];
foreach ($missionGroups as $group) {
    $allMissions = array_merge($allMissions, $group['missions'] ?? []);
}
$completedCount = 0;
foreach ($allMissions as $mission) {
    if (!empty($mission['is_completed'])) $completedCount++;
}
$totalPercent = count($allMissions) > 0 ? ($completedCount / count($allMissions)) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Misje i Cele - Platforma Testowa</title>
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

            <main role="main" class="content-body">
                <div class="container-fluid p-0">
                    
                    <div class="mb-4 animate-in">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="fw-bold mb-1">Misje i Cele</h2>
                                <p class="text-muted">Realizuj wyzwania dzienne, tygodniowe i miesięczne, aby awansować w rankingu.</p>
                            </div>
                            <div class="text-end">
                                <div class="small text-muted mb-1">Ukończone misje: <span class="fw-bold text-primary"><?php echo $completedCount; ?>/<?php echo count($allMissions); ?></span></div>
                                <div class="progress" style="width: 150px; height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $totalPercent; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($missionGroups as $period => $missionData): ?>
                        <?php
                            $meta = $missionMeta[$period];
                            $resetInterval = (new DateTime())->diff($meta['reset']);
                            $resetText = $period === 'daily'
                                ? $resetInterval->format('%h h %i m')
                                : $resetInterval->format('%a dni %h h');
                        ?>
                        <section class="mb-5">
                            <div class="d-flex align-items-end justify-content-between flex-wrap gap-3 mb-3">
                                <div>
                                    <h3 class="fw-800 mb-1"><i class="bi <?php echo $meta['icon']; ?> text-<?php echo $meta['color']; ?> me-2"></i><?php echo $meta['title']; ?></h3>
                                    <p class="text-muted mb-0"><?php echo $meta['subtitle']; ?></p>
                                </div>
                                <span class="badge bg-<?php echo $meta['color']; ?> bg-opacity-10 text-<?php echo $meta['color']; ?> rounded-pill px-3 py-2">Reset za <?php echo $resetText; ?></span>
                            </div>
                            <div class="row g-4">
                                <?php foreach (($missionData['missions'] ?? []) as $m): ?>
                                <?php 
                                    $key = $m['mission_type'];
                                    $config = $missionData['pool'][$key] ?? ['title' => $m['mission_description'], 'desc' => $m['mission_description'], 'reward_xp' => $m['xp_reward'], 'icon' => 'bi-flag', 'color' => $meta['color']];
                                    $targetValue = max(1, (int)$m['target_value']);
                                    $percent = min(100, round(($m['current_value'] / $targetValue) * 100));
                                    $isDone = $m['is_completed'];
                                ?>
                                <div class="col-md-6 col-xl-4">
                                    <div class="dashboard-panel h-100 animate-in <?php echo $isDone ? 'border-success' : ''; ?>" style="position: relative;">
                                        <?php if ($isDone): ?>
                                            <div class="position-absolute top-0 end-0 p-3">
                                                <i class="bi bi-patch-check-fill text-success fs-4"></i>
                                            </div>
                                        <?php endif; ?>

                                        <div class="d-flex align-items-center gap-3 mb-3">
                                            <div class="mission-icon bg-<?php echo $config['color']; ?> bg-opacity-10 text-<?php echo $config['color']; ?> rounded-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.5rem;">
                                                <i class="bi <?php echo $config['icon']; ?>"></i>
                                            </div>
                                            <div>
                                                <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($config['title']); ?></h5>
                                                <span class="badge bg-dark text-white shadow-sm small">
                                                    +<?php echo (int)$config['reward_xp']; ?> XP
                                                </span>
                                            </div>
                                        </div>
                                        <p class="text-muted small mb-4">
                                            <?php echo htmlspecialchars(str_replace('{target}', $targetValue, $config['desc'])); ?>
                                        </p>
                                        
                                        <div class="mt-auto">
                                            <div class="d-flex justify-content-between mb-2 small">
                                                <span class="text-muted">Postęp: <span class="fw-bold text-dark"><?php echo round($m['current_value'], 1); ?> / <?php echo $targetValue; ?></span></span>
                                                <span class="fw-bold text-<?php echo $config['color']; ?>"><?php echo $percent; ?>%</span>
                                            </div>
                                            <div class="progress" style="height: 10px; border-radius: 5px;">
                                                <div class="progress-bar bg-<?php echo $config['color']; ?> <?php echo $isDone ? '' : 'progress-bar-striped progress-bar-animated'; ?>" 
                                                     role="progressbar" 
                                                     aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"
                                                     style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                        
                    <div class="row g-4">
                        <div class="col-12 mt-4">
                            <div class="dashboard-panel text-center py-5 shadow-lg border-0" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-color-dark) 100%); color: white;">
                                <i class="bi bi-trophy-fill text-white opacity-75" style="font-size: 4rem;"></i>
                                <h3 class="fw-800 mt-3 text-white">Rywalizuj z innymi</h3>
                                <p class="opacity-90 mb-4" style="max-width: 600px; margin-left: auto; margin-right: auto; font-size: 1.1rem;">
                                    Zdobywaj XP za misje, aby piąć się w górę w ogólnym rankingu platformy i pokazać wszystkim swoje umiejętności.
                                </p>
                                <a href="ranking.php" class="btn btn-light btn-lg shadow-sm px-5 rounded-pill fw-bold text-primary">
                                    <i class="bi bi-bar-chart-fill me-2"></i>Zobacz Ranking
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
</body>
</html>
