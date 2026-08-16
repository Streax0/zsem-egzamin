<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

class DbBackup
{
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

    public function createBackup(PDO|string|null $arg1 = null, ?string $arg2 = null): array
    {
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
                      "-- ZSEM Tech Platform Database Backup\n" .
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

        return [
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
