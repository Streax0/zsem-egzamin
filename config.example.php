<?php
/**
 * Przykładowa konfiguracja bazy danych dla platformy INF.02
 * Skopiuj ten plik do config/db.php i uzupełnij dane swojej bazy
 */

// Host bazy danych (lokalnie zwykle 'localhost')
define('DB_HOST', 'localhost');

// Nazwa bazy danych
define('DB_NAME', 'inf02_platform');

// Użytkownik bazy danych
define('DB_USER', 'root');

// Hasło użytkownika bazy danych
define('DB_PASS', '');

// Opcjonalnie: Charset (utf8mb4 zalecane)
define('DB_CHARSET', 'utf8mb4');

/**
 * WAŻNE: Bezpieczeństwo
 *
 * 1. Nigdy nie przechowuj pliku config/db.php (z prawdziwymi danymi) w katalogu
 *    dostępnym publicznie (webroot). Przechowuj go poza głównym katalogiem www.
 *
 * 2. Używaj zmiennych środowiskowych w środowisku produkcyjnym, aby uniknąć
 *    przechowywania danych uwierzytelniających w repozytorium kodu:
 *
 *    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
 *    define('DB_NAME', getenv('DB_NAME'));
 *    define('DB_USER', getenv('DB_USER'));
 *    define('DB_PASS', getenv('DB_PASS'));
 *    define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
 *
 * 3. Upewnij się, że plik db.php ma odpowiednie uprawnienia (tylko do odczytu
 *    dla użytkownika serwera www) i nie jest dostępny przez sieć.
 */
