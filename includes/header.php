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
