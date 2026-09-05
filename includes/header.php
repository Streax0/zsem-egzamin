<?php
if (!isset($pageTitle)) {
    $pageTitle = 'ZSEM Tech';
}
if (!isset($base_url)) {
    $base_url = file_exists('config/db.php') ? '' : '../';
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="<?php echo htmlspecialchars($base_url); ?>zsemtech_profile.ico" type="image/x-icon">
    <link rel="manifest" href="<?php echo htmlspecialchars($base_url); ?>assets/manifest.webmanifest">
    <meta name="theme-color" content="#4f46e5">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta property="og:site_name" content="ZSEM Tech">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?php echo htmlspecialchars($base_url); ?>zsemtech_profile.ico">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="<?php echo htmlspecialchars($base_url); ?>assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css', rtrim($base_url, '/'))); ?>">
    <?php if (isset($extraCss)): ?>
        <?php foreach ($extraCss as $cssFile): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl($cssFile, rtrim($base_url, '/'))); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (function_exists('devtoolsPolicyMetaTag')): echo devtoolsPolicyMetaTag(); else: ?>
        <meta name="devtools-policy" content="<?php echo (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)) ? 'allow' : 'deny'; ?>">
        <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)): ?><script>window.__ZSEM_DEVTOOLS_ENABLED=true;</script><?php endif; ?>
    <?php endif; ?>
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/devtools-guard.js', rtrim($base_url, '/'))); ?>"></script>
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js', rtrim($base_url, '/'))); ?>"></script>
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/utils.js', rtrim($base_url, '/'))); ?>"></script>
    <?php if (isset($extraHead)): echo $extraHead; endif; ?>
</head>
<body <?php echo isset($bodyClasses) ? 'class="' . implode(' ', $bodyClasses) . '"' : ''; ?> <?php echo isset($bodyAttributes) ? $bodyAttributes : ''; ?>>
<?php
// Maintenance Mode Administrator Warning Banner
if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)) {
    $engineConfigPath = __DIR__ . '/../data/config/engine_config.json';
    if (file_exists($engineConfigPath)) {
        $rawConfig = @file_get_contents($engineConfigPath);
        $parsedConfig = $rawConfig ? json_decode($rawConfig, true) : null;
        if (!empty($parsedConfig['maintenance_mode'])) {
            $mMsg = !empty($parsedConfig['maintenance_message']) ? htmlspecialchars($parsedConfig['maintenance_message'], ENT_QUOTES, 'UTF-8') : 'Prace techniczne w toku.';
            $mUntil = !empty($parsedConfig['maintenance_until']) ? ' (Do: ' . htmlspecialchars(date('d.m H:i', strtotime($parsedConfig['maintenance_until'])), ENT_QUOTES, 'UTF-8') . ')' : '';
            $engineAdminUrl = htmlspecialchars($base_url) . 'admin/engine.php';
            echo '<div class="alert alert-danger m-0 rounded-0 border-0 border-bottom border-danger-subtle d-flex align-items-center justify-content-between px-3 py-2 small shadow-sm" style="z-index: 99999; position: relative;" role="alert">';
            echo '  <div class="d-flex align-items-center gap-2 font-monospace fw-bold">';
            echo '      <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>';
            echo '      <span><strong>TRYB KONSERWACJI AKTYWNY</strong> — Serwis jest zablokowany dla zwykłych użytkowników. ' . $mMsg . $mUntil . '</span>';
            echo '  </div>';
            echo '  <a href="' . $engineAdminUrl . '" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-semibold flex-shrink-0">Zarządzaj</a>';
            echo '</div>';
        }
    }
}
?>
