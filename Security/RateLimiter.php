<?php

function securityRateLimitDirectory(): ?string {
    $configured = getenv('APP_RATE_LIMIT_DIR');
    if ($configured === false || trim((string)$configured) === '') {
        $configured = $_ENV['APP_RATE_LIMIT_DIR'] ?? '';
    }
    $directory = trim((string)$configured);
    if ($directory === '') {
        $scope = substr(hash('sha256', dirname(__DIR__)), 0, 12);
        $directory = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'zsemtech-rate-limits-' . $scope;
    }
    if (str_contains($directory, "\0") || is_link($directory)) {
        return null;
    }
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        return null;
    }
    return is_writable($directory) ? $directory : null;
}

function securityPruneRateLimitFiles(string $directory, int $now): void {
    $markerPath = $directory . DIRECTORY_SEPARATOR . '.cleanup';
    $marker = @fopen($markerPath, 'c+b');
    if ($marker === false) {
        return;
    }

    $locked = false;
    try {
        $locked = flock($marker, LOCK_EX | LOCK_NB);
        if (!$locked) {
            return;
        }
        rewind($marker);
        $lastCleanup = (int)trim((string)stream_get_contents($marker));
        if ($lastCleanup > $now - 3600) {
            return;
        }

        if (!ftruncate($marker, 0)) {
            return;
        }
        rewind($marker);
        $timestamp = (string)$now;
        if (fwrite($marker, $timestamp) !== strlen($timestamp) || !fflush($marker)) {
            return;
        }

        $deleted = 0;
        foreach (new DirectoryIterator($directory) as $file) {
            if (!$file->isFile() || !preg_match('/^[a-f0-9]{64}\.json$/', $file->getFilename())) {
                continue;
            }
            if ($file->getMTime() < $now - 172800) {
                if (@unlink($file->getPathname())) {
                    $deleted++;
                    if ($deleted >= 500) {
                        break;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Shared rate-limit cleanup failed: ' . $e->getMessage());
    } finally {
        if ($locked) {
            flock($marker, LOCK_UN);
        }
        fclose($marker);
    }
}

function securityConsumeRateLimit(string $bucket, int $limit, int $windowSeconds): array {
    $limit = max(1, $limit);
    $windowSeconds = max(1, $windowSeconds);
    $now = time();
    $key = hash('sha256', $bucket);
    $directory = securityRateLimitDirectory();
    if ($directory === null) {
        error_log('Shared rate-limit directory is unavailable.');
        return ['allowed' => false, 'remaining' => 0, 'retry_after' => $windowSeconds];
    }
    securityPruneRateLimitFiles($directory, $now);

    $path = $directory . DIRECTORY_SEPARATOR . $key . '.json';
    $handle = @fopen($path, 'c+b');
    if ($handle === false) {
        error_log('Shared rate-limit state cannot be opened.');
        return ['allowed' => false, 'remaining' => 0, 'retry_after' => $windowSeconds];
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            return ['allowed' => false, 'remaining' => 0, 'retry_after' => $windowSeconds];
        }
        rewind($handle);
        $raw = stream_get_contents($handle);
        $entry = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($entry) || (int)($entry['reset_at'] ?? 0) <= $now) {
            $entry = ['count' => 0, 'reset_at' => $now + $windowSeconds];
        }

        $entry['count'] = (int)$entry['count'] + 1;
        $payload = json_encode($entry, JSON_UNESCAPED_SLASHES);
        if (!ftruncate($handle, 0)) {
            error_log('Shared rate-limit state cannot be truncated.');
            return ['allowed' => false, 'remaining' => 0, 'retry_after' => $windowSeconds];
        }
        rewind($handle);
        if ($payload === false || fwrite($handle, $payload) !== strlen($payload) || !fflush($handle)) {
            error_log('Shared rate-limit state cannot be persisted.');
            return ['allowed' => false, 'remaining' => 0, 'retry_after' => $windowSeconds];
        }

        return [
            'allowed' => (int)$entry['count'] <= $limit,
            'remaining' => max(0, $limit - (int)$entry['count']),
            'retry_after' => max(0, (int)$entry['reset_at'] - $now),
        ];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
