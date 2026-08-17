<?php
/**
 * Database Backup Decryption CLI Utility
 *
 * Usage:
 *   php cron/decrypt_backup.php <encrypted_backup_file.enc> [output_file.sql] [passphrase_or_key]
 *
 * Examples:
 *   php cron/decrypt_backup.php data/backups/backup_2026-08-17_120000_a1b2c3d4.sql.gz.enc
 *   php cron/decrypt_backup.php data/backups/backup_2026-08-17_120000_a1b2c3d4.sql.gz.enc restored_database.sql
 *   php cron/decrypt_backup.php backup.sql.gz.enc restored.sql my_secret_custom_key_123
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/autoloader.php';

use App\Core\DbBackup;

$isCli = PHP_SAPI === 'cli';

$argvInput = $argv ?? [];
$encryptedFile = $argvInput[1] ?? null;
$destFile = $argvInput[2] ?? null;
$customKey = $argvInput[3] ?? null;

if (!$encryptedFile) {
    echo "========================================================================\n";
    echo " ZSEM Tech — Narzędzie Odszyfrowywania Kopii Zapasowych Bazy Danych\n";
    echo " Standard: AES-256-GCM (AEAD) + PBKDF2 HMAC-SHA256 (100k rounds)\n";
    echo "========================================================================\n\n";
    echo "Użycie:\n";
    echo "  php cron/decrypt_backup.php <ścieżka_do_pliku.enc> [plik_wyjściowy.sql] [hasło/klucz]\n\n";
    echo "Przykłady:\n";
    echo "  php cron/decrypt_backup.php data/backups/backup_2026-08-17_133000_abcd.sql.gz.enc\n";
    echo "  php cron/decrypt_backup.php data/backups/backup_2026-08-17_133000_abcd.sql.gz.enc restored_db.sql\n\n";
    if ($isCli) exit(1); else exit;
}

if (!file_exists($encryptedFile)) {
    $resolved = dirname(__DIR__) . '/' . ltrim($encryptedFile, '/\\');
    if (file_exists($resolved)) {
        $encryptedFile = $resolved;
    } else {
        echo "BŁĄD: Podany plik zaszyfrowanej kopii nie istnieje: {$encryptedFile}\n";
        if ($isCli) exit(1); else exit;
    }
}

echo "--- Rozpoczynanie odszyfrowywania kopii bazy danych ---\n";
echo "Plik źródłowy: {$encryptedFile}\n";
echo "Rozmiar zaszyfrowany: " . round(filesize($encryptedFile) / 1024, 2) . " KB\n";

try {
    $backupService = new DbBackup();

    if ($customKey === null || trim($customKey) === '') {
        $customKey = $backupService->getOrGenerateEncryptionKey();
        echo "Klucz szyfrowania: wczytany ze zmiennych środowiskowych serwera (BACKUP_ENCRYPTION_KEY).\n";
    } else {
        echo "Klucz szyfrowania: podany przez argument CLI.\n";
    }

    if ($destFile === null) {
        if (str_ends_with($encryptedFile, '.enc')) {
            $destFile = substr($encryptedFile, 0, -4);
        } else {
            $destFile = $encryptedFile . '.decrypted.sql';
        }
    }

    $t0 = microtime(true);
    $result = $backupService->decryptFile($encryptedFile, $destFile, $customKey);
    $duration = round(microtime(true) - $t0, 3);

    echo "\n[SUKCES] Odszyfrowano pomyślnie i zweryfikowano tag autentyczności (AEAD)!\n";
    echo "  Plik wynikowy : {$result['dest_file']}\n";
    echo "  Rozmiar       : {$result['size_formatted']}\n";
    echo "  Suma SHA-256  : {$result['sha256']}\n";
    echo "  Czas operacji : {$duration}s\n";
    echo "---------------------------------------------------------\n";
    echo "Gotowy plik możesz zaimportować do MySQL za pomocą:\n";
    echo "  mysql -u root -p nazwa_bazy < " . escapeshellarg($result['dest_file']) . "\n\n";

} catch (Throwable $e) {
    echo "\n[BŁĄD KRYTYCZNY] Odszyfrowanie nie powiodło się:\n";
    echo "  " . $e->getMessage() . "\n\n";
    if ($isCli) exit(1);
}
