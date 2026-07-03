<?php
/**
 * PSR-4 Autoloader
 */
spl_autoload_register(function ($class) {
    // Wsparcie dla App\
    $prefixApp = 'App\\';
    $baseDirApp = __DIR__ . '/../src/App/';
    $lenApp = strlen($prefixApp);
    if (strncmp($prefixApp, $class, $lenApp) === 0) {
        $relative_class = substr($class, $lenApp);
        $file = $baseDirApp . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }

    // Wsparcie dla lbuchs\WebAuthn\
    $prefixWebAuthn = 'lbuchs\\WebAuthn\\';
    $baseDirWebAuthn = __DIR__ . '/WebAuthn/';
    $lenWebAuthn = strlen($prefixWebAuthn);
    if (strncmp($prefixWebAuthn, $class, $lenWebAuthn) === 0) {
        $relative_class = substr($class, $lenWebAuthn);
        $file = $baseDirWebAuthn . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});
