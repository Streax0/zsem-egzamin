<?php
declare(strict_types=1);

/**
 * Ten plik jest wyłącznie wskazówką konfiguracyjną.
 *
 * Nie kopiuj go do config/db.php i nie wpisuj haseł do plików PHP.
 * Utwardzony config/db.php musi pozostać częścią aplikacji. Skopiuj
 * .env.example do ignorowanego przez Git pliku .env albo ustaw poniższe
 * zmienne bezpośrednio w konfiguracji serwera:
 *
 * APP_ENV=local
 * APP_RUNTIME_SCHEMA_UPDATES=false
 * MYSQL_HOST=localhost
 * MYSQL_PORT=3306
 * MYSQL_SOCKET=
 * MYSQL_DATABASE=inf02_platform
 * MYSQL_USER=root
 * MYSQL_PASSWORD=
 * MYSQL_CONNECT_TIMEOUT=5
 * MYSQL_SSL_CA=
 * MYSQL_SSL_CERT=
 * MYSQL_SSL_KEY=
 *
 * Na produkcji użyj APP_ENV=production, osobnego użytkownika bazy z
 * minimalnymi uprawnieniami i niepustego hasła. Dla zdalnej bazy skonfiguruj
 * zaufany certyfikat CA przez MYSQL_SSL_CA. Zmiany schematu wykonuj poza
 * requestami HTTP, importując full_schema.sql lub kontrolowaną migrację CLI.
 */

if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    http_response_code(404);
    header('Cache-Control: no-store');
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    exit;
}
return;
