<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

class DbBackup
{
    public const MAGIC_HEADER = 'ZSEMENC001';
    public const CIPHER_ALGO = 'aes-256-gcm';
    public const PBKDF2_ROUNDS = 100000;
    public const AAD_TAG = 'ZSEM_TECH_BACKUP_V1';

    private ?PDO $pdo = null;
    private string $backupDir;

    public function __construct(PDO|string|null $pdoOrDir = null, ?string $backupDir = null)
    {
        if ($pdoOrDir instanceof PDO) {
            $this->pdo = $pdoOrDir;
            $this->backupDir = rtrim($backupDir ?? __DIR__ . '/../../../data/backups', '/\\');
        } else {
            $this->pdo = null;
            $this->backupDir = rtrim((string)($pdoOrDir ?? ($backupDir ?? __DIR__ . '/../../../data/backups')), '/\\');
        }
        $this->ensureSecureBackupDirectory();
    }

    public function getBackupDir(): string
    {
        return $this->backupDir;
    }

    private function ensureSecureBackupDirectory(): void
    {
        if (!is_dir($this->backupDir)) {
            if (!@mkdir($this->backupDir, 0755, true) && !is_dir($this->backupDir)) {
                throw new RuntimeException('Cannot create backup directory: ' . $this->backupDir);
            }
        }

        $htaccessPath = $this->backupDir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "# Deny all direct web requests to database backup archives\n" .
                "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n" .
                "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n";
            @file_put_contents($htaccessPath, $htaccessContent, LOCK_EX);
        }

        $indexHtml = $this->backupDir . '/index.html';
        if (!file_exists($indexHtml)) {
            @file_put_contents($indexHtml, '', LOCK_EX);
        }
    }

    /**
     * Retrieve server-level encryption master secret from environment / config.
     * Never stores secrets in unencrypted files on disk.
     */
    public function getOrGenerateEncryptionKey(): string
    {
        // 1. Check BACKUP_ENCRYPTION_KEY in .env / system env
        if (function_exists('configValue')) {
            $envKey = configValue('BACKUP_ENCRYPTION_KEY', '');
            if ($envKey !== '') {
                return $envKey;
            }
        }

        $envKey = getenv('BACKUP_ENCRYPTION_KEY') ?: ($_ENV['BACKUP_ENCRYPTION_KEY'] ?? '');
        if (is_string($envKey) && trim($envKey) !== '') {
            return trim($envKey);
        }

        // 2. Fallback: derive securely from JWT_SECRET or APP_SECRET without saving to disk
        $jwtSecret = function_exists('configValue') ? configValue('JWT_SECRET', '') : (getenv('JWT_SECRET') ?: '');
        if ($jwtSecret !== '') {
            return hash_hmac('sha256', 'zsem_db_backup_master_key_salt_v1', $jwtSecret);
        }

        // 3. Fallback server secret
        return hash_hmac('sha256', 'zsem_fallback_backup_salt', php_uname() . __DIR__);
    }

    /**
     * Create full database backup (compressed + AES-256-GCM AEAD encrypted).
     */
    public function createBackup(
        PDO|string|null $arg1 = null, 
        ?string $arg2 = null, 
        bool $encrypt = true, 
        ?string $passphrase = null
    ): array {
        $startTime = microtime(true);
        $pdo = $this->pdo;
        $targetDir = $this->backupDir;

        if ($arg1 instanceof PDO) {
            $pdo = $arg1;
            if ($arg2 !== null && is_string($arg2)) {
                $targetDir = rtrim($arg2, '/\\');
            }
        } elseif (is_string($arg1)) {
            if (is_dir($arg1)) {
                $targetDir = rtrim($arg1, '/\\');
            }
        }

        if (!$pdo instanceof PDO) {
            global $pdo;
            if ($pdo instanceof PDO) {
                $this->pdo = $pdo;
            } else {
                throw new RuntimeException('No PDO connection provided for database backup.');
            }
        }

        $this->backupDir = $targetDir;
        $this->ensureSecureBackupDirectory();

        $randomSuffix = bin2hex(random_bytes(4));
        $filename = 'backup_' . date('Y-m-d_His') . '_' . $randomSuffix . '.sql.gz';
        $filePath = $targetDir . '/' . $filename;

        if (!function_exists('gzopen')) {
            throw new RuntimeException('Zlib extension is required for compressed backups (gzopen not found).');
        }

        $gz = @gzopen($filePath, 'wb9');
        if (!$gz) {
            throw new RuntimeException('Failed to open backup file for writing: ' . $filePath);
        }

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $tablesCount = 0;
        $totalRows = 0;

        try {
            $header = "-- ========================================================\n" .
                      "-- ZSEM Tech Platform Full Database Backup (Includes All User Data)\n" .
                      "-- Generated: " . gmdate('Y-m-d H:i:s') . " UTC\n" .
                      "-- Driver: " . $driver . "\n" .
                      "-- ========================================================\n\n";

            if ($driver !== 'sqlite') {
                $header .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n" .
                           "SET time_zone = \"+00:00\";\n" .
                           "SET NAMES utf8mb4;\n" .
                           "SET FOREIGN_KEY_CHECKS = 0;\n\n";
            }

            gzwrite($gz, $header);

            $tables = $this->getTableList($pdo, $driver);
            $tablesCount = count($tables);

            foreach ($tables as $table) {
                try {
                    $createSql = $this->getTableCreateSql($pdo, $table, $driver);
                    gzwrite($gz, "\n-- --------------------------------------------------------\n");
                    gzwrite($gz, "-- Table structure for table `{$table}`\n");
                    gzwrite($gz, "-- --------------------------------------------------------\n");
                    gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n");
                    gzwrite($gz, $createSql . ";\n\n");

                    $rowsExported = $this->streamTableData($pdo, $gz, $table);
                    $totalRows += $rowsExported;
                } catch (Throwable $tableEx) {
                    gzwrite($gz, "\n-- WARNING: Skipped table `{$table}`: " . str_replace(["\r", "\n"], ' ', $tableEx->getMessage()) . "\n");
                    error_log("DbBackup: Skipped table `{$table}`: " . $tableEx->getMessage());
                }
            }

            if ($driver !== 'sqlite') {
                gzwrite($gz, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
            }
            gzwrite($gz, "-- Dump completed on " . gmdate('Y-m-d H:i:s') . " UTC\n");

            gzclose($gz);
        } catch (Throwable $e) {
            @gzclose($gz);
            @unlink($filePath);
            throw new RuntimeException('Database backup failed: ' . $e->getMessage(), 0, $e);
        }

        $fileSize = (int)@filesize($filePath);
        $duration = round(microtime(true) - $startTime, 3);

        $result = [
            'success'          => true,
            'file'             => $filePath,
            'filename'         => $filename,
            'size'             => $fileSize,
            'size_formatted'   => $this->formatBytes($fileSize),
            'tables'           => $tablesCount,
            'tables_count'     => $tablesCount,
            'rows_count'       => $totalRows,
            'duration_seconds' => $duration,
            'compressed'       => true,
            'encrypted'        => false,
        ];

        // Perform AES-256-GCM AEAD Encryption
        if ($encrypt) {
            $encKey = (is_string($passphrase) && trim($passphrase) !== '') ? trim($passphrase) : $this->getOrGenerateEncryptionKey();
            $encryptedFile = $filePath . '.enc';
            $encResult = $this->encryptFile($filePath, $encryptedFile, $encKey);

            $result['encrypted'] = true;
            $result['encrypted_file'] = $encryptedFile;
            $result['encrypted_filename'] = basename($encryptedFile);
            $result['encrypted_size'] = $encResult['size'];
            $result['encrypted_size_formatted'] = $this->formatBytes($encResult['size']);
            $result['encryption_algorithm'] = 'AES-256-GCM (AEAD)';
            $result['sha256_checksum'] = hash_file('sha256', $encryptedFile);
        }

        return $result;
    }

    /**
     * Encrypt any file using AES-256-GCM with PBKDF2 HMAC-SHA256 key derivation.
     *
     * Binary Format:
     * - 10 bytes: MAGIC_HEADER ('ZSEMENC001')
     * - 32 bytes: Random Salt for PBKDF2
     * - 12 bytes: Random IV/Nonce for AES-256-GCM
     * - 16 bytes: GCM Authentication Tag (AEAD)
     * - N bytes : Ciphertext
     */
    public function encryptFile(string $sourceFile, ?string $destFile = null, ?string $passphrase = null): array
    {
        if (!file_exists($sourceFile) || !is_readable($sourceFile)) {
            throw new RuntimeException("Source file not found or unreadable: {$sourceFile}");
        }

        $passphrase = (is_string($passphrase) && trim($passphrase) !== '') ? trim($passphrase) : $this->getOrGenerateEncryptionKey();
        $destFile = $destFile ?? ($sourceFile . '.enc');

        $plaintext = file_get_contents($sourceFile);
        if ($plaintext === false) {
            throw new RuntimeException("Failed to read source file: {$sourceFile}");
        }

        $salt = random_bytes(32);
        $iv = random_bytes(12);
        $derivedKey = hash_pbkdf2('sha256', $passphrase, $salt, self::PBKDF2_ROUNDS, 32, true);

        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER_ALGO,
            $derivedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD_TAG,
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException("OpenSSL AES-256-GCM encryption failed: " . (openssl_error_string() ?: 'unknown error'));
        }

        $binaryPayload = self::MAGIC_HEADER . $salt . $iv . $tag . $ciphertext;
        $written = @file_put_contents($destFile, $binaryPayload, LOCK_EX);

        if ($written === false) {
            throw new RuntimeException("Failed to write encrypted backup file: {$destFile}");
        }

        return [
            'success' => true,
            'dest_file' => $destFile,
            'size' => strlen($binaryPayload),
            'algorithm' => self::CIPHER_ALGO,
            'sha256' => hash_file('sha256', $destFile),
        ];
    }

    /**
     * Decrypt an AES-256-GCM encrypted backup file and verify AEAD authenticity tag.
     */
    public function decryptFile(string $encryptedFile, ?string $destFile = null, ?string $passphrase = null): array
    {
        if (!file_exists($encryptedFile) || !is_readable($encryptedFile)) {
            throw new RuntimeException("Encrypted backup file not found: {$encryptedFile}");
        }

        $passphrase = (is_string($passphrase) && trim($passphrase) !== '') ? trim($passphrase) : $this->getOrGenerateEncryptionKey();
        $data = file_get_contents($encryptedFile);
        if ($data === false) {
            throw new RuntimeException("Failed to read encrypted file: {$encryptedFile}");
        }

        $headerLen = strlen(self::MAGIC_HEADER);
        if (strlen($data) < ($headerLen + 32 + 12 + 16 + 1)) {
            throw new RuntimeException("Corrupted or invalid encrypted file header (file too small).");
        }

        $header = substr($data, 0, $headerLen);
        if ($header !== self::MAGIC_HEADER) {
            throw new RuntimeException("Invalid file magic header. Expected '" . self::MAGIC_HEADER . "', got '" . substr($header, 0, 10) . "'");
        }

        $offset = $headerLen;
        $salt = substr($data, $offset, 32);
        $offset += 32;
        $iv = substr($data, $offset, 12);
        $offset += 12;
        $tag = substr($data, $offset, 16);
        $offset += 16;
        $ciphertext = substr($data, $offset);

        $derivedKey = hash_pbkdf2('sha256', $passphrase, $salt, self::PBKDF2_ROUNDS, 32, true);

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER_ALGO,
            $derivedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD_TAG
        );

        if ($decrypted === false) {
            throw new RuntimeException("Błąd odszyfrowania: niepoprawne hasło/klucz szyfrowania lub uszkodzony plik kopii (niezgodność tagu AEAD).");
        }

        if ($destFile === null) {
            if (str_ends_with($encryptedFile, '.enc')) {
                $destFile = substr($encryptedFile, 0, -4);
            } else {
                $destFile = $encryptedFile . '.decrypted';
            }
        }

        // If target file requested is pure .sql and decrypted payload is .gz, decompress automatically
        if (str_ends_with($destFile, '.sql') && str_starts_with($decrypted, "\x1f\x8b")) {
            $uncompressed = @gzdecode($decrypted);
            if ($uncompressed !== false) {
                $decrypted = $uncompressed;
            }
        }

        $written = @file_put_contents($destFile, $decrypted, LOCK_EX);
        if ($written === false) {
            throw new RuntimeException("Failed to write decrypted file: {$destFile}");
        }

        return [
            'success' => true,
            'dest_file' => $destFile,
            'size' => strlen($decrypted),
            'size_formatted' => $this->formatBytes(strlen($decrypted)),
            'sha256' => hash_file('sha256', $destFile),
        ];
    }

    private function getTableList(PDO $pdo, string $driver): array
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        }

        $stmt = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        $tables = [];
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tables[] = (string)$row[0];
            }
        }
        return $tables;
    }

    private function getTableCreateSql(PDO $pdo, string $table, string $driver): string
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?");
            $stmt->execute([$table]);
            return (string)$stmt->fetchColumn();
        }

        $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : null;
        return $row ? (string)$row[1] : "CREATE TABLE `{$table}` ()";
    }

    private function streamTableData(PDO $pdo, $gz, string $table, int $chunkSize = 500): int
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $colStmt = $pdo->query("PRAGMA table_info(`{$table}`)");
            $cols = $colStmt ? $colStmt->fetchAll(PDO::FETCH_COLUMN, 1) : [];
        } else {
            $colStmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            $cols = $colStmt ? $colStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        }

        if (!empty($cols)) {
            $colList = implode(', ', array_map(fn($c) => "`{$c}`", $cols));
            $stmt = $pdo->query("SELECT {$colList} FROM `{$table}`");
        } else {
            $stmt = $pdo->query("SELECT rowid FROM `{$table}`");
        }

        if (!$stmt) {
            return 0;
        }

        $count = 0;
        $batch = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $count++;
            $values = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $values[] = 'NULL';
                } elseif (is_int($val) || is_float($val)) {
                    $values[] = (string)$val;
                } else {
                    $values[] = $pdo->quote((string)$val);
                }
            }
            $batch[] = '(' . implode(', ', $values) . ')';

            if (count($batch) >= $chunkSize) {
                gzwrite($gz, "INSERT INTO `{$table}` VALUES \n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }

        if (!empty($batch)) {
            gzwrite($gz, "INSERT INTO `{$table}` VALUES \n" . implode(",\n", $batch) . ";\n");
        }

        return $count;
    }

    public function cleanupOldBackups(int $retentionDays = 7, ?string $dir = null): int
    {
        $targetDir = $dir !== null ? rtrim($dir, '/\\') : $this->backupDir;
        $cutoff = time() - ($retentionDays * 86400);
        $files = glob($targetDir . '/backup_*.sql.gz*');
        $deleted = 0;

        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    $mtime = filemtime($file);
                    if ($mtime !== false && $mtime < $cutoff) {
                        if (@unlink($file)) {
                            $deleted++;
                        }
                    }
                }
            }
        }
        return $deleted;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
        return round($bytes / 1048576, 2) . ' MB';
    }
}
