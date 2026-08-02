<?php

namespace App\Core;

class CacheManager
{
    private string $cacheDir;
    private int $hits = 0;
    private int $misses = 0;
    private string $apcuPrefix = 'app_cache_';

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = rtrim($cacheDir ?? __DIR__ . '/../../../data/cache', '/\\');
        $this->ensureDirectoryExists($this->cacheDir);
        $this->ensureDirectoryExists($this->cacheDir . '/assets');
        $this->apcuPrefix = 'app_cache_' . md5($this->cacheDir) . '_';
    }

    private function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    public function isApcuAvailable(): bool
    {
        return function_exists('apcu_fetch') && (bool)ini_get('apc.enabled');
    }

    private function getFilePath(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir . '/cache_' . $hash . '.json';
    }

    public function get(string $key, $default = null)
    {
        // 1. Try APCu
        if ($this->isApcuAvailable()) {
            $success = false;
            $cached = apcu_fetch($this->apcuPrefix . $key, $success);
            if ($success) {
                $this->hits++;
                return $cached;
            }
        }

        // 2. Try File fallback
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath)) {
            $this->misses++;
            return $default;
        }

        $fp = @fopen($filePath, 'rb');
        if (!$fp) {
            $this->misses++;
            return $default;
        }

        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($content === false || $content === '') {
            $this->misses++;
            return $default;
        }

        $payload = json_decode($content, true);
        if (!is_array($payload) || !isset($payload['expires_at'], $payload['value'])) {
            $this->misses++;
            return $default;
        }

        if ($payload['expires_at'] > 0 && time() > $payload['expires_at']) {
            @unlink($filePath);
            if ($this->isApcuAvailable()) {
                apcu_delete($this->apcuPrefix . $key);
            }
            $this->misses++;
            return $default;
        }

        $value = @unserialize($payload['value'], ['allowed_classes' => true]);
        if ($value === false && $payload['value'] !== serialize(false)) {
            $this->misses++;
            return $default;
        }

        // Repopulate APCu if available
        if ($this->isApcuAvailable()) {
            $ttl = $payload['expires_at'] > 0 ? max(1, $payload['expires_at'] - time()) : 0;
            apcu_store($this->apcuPrefix . $key, $value, $ttl);
        }

        $this->hits++;
        return $value;
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $expiresAt = $ttl > 0 ? time() + $ttl : 0;

        // Save to APCu if available
        if ($this->isApcuAvailable()) {
            apcu_store($this->apcuPrefix . $key, $value, $ttl);
        }

        // Save to File
        $filePath = $this->getFilePath($key);
        $payload = [
            'key' => $key,
            'expires_at' => $expiresAt,
            'value' => serialize($value),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $fp = @fopen($filePath, 'c+b');
        if (!$fp) {
            return false;
        }

        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $json);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        }

        fclose($fp);
        return false;
    }

    public function delete(string $key): bool
    {
        $deleted = true;

        if ($this->isApcuAvailable()) {
            apcu_delete($this->apcuPrefix . $key);
        }

        $filePath = $this->getFilePath($key);
        if (file_exists($filePath)) {
            $deleted = @unlink($filePath);
        }

        return $deleted;
    }

    public function clear(string $type = 'all'): bool
    {
        $success = true;

        if (in_array($type, ['apcu', 'all'], true)) {
            if ($this->isApcuAvailable()) {
                apcu_clear_cache();
            }
        }

        if (in_array($type, ['file', 'all'], true)) {
            $files = glob($this->cacheDir . '/cache_*.json');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        if (!@unlink($file)) {
                            $success = false;
                        }
                    }
                }
            }
        }

        if (in_array($type, ['assets', 'all'], true)) {
            $assetsDir = $this->cacheDir . '/assets';
            if (is_dir($assetsDir)) {
                $files = glob($assetsDir . '/*');
                if (is_array($files)) {
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            if (!@unlink($file)) {
                                $success = false;
                            }
                        }
                    }
                }
            }
        }

        return $success;
    }

    public function remember(string $key, int $ttl, callable $callback)
    {
        $value = $this->get($key, null);
        if ($value !== null) {
            return $value;
        }

        $computed = $callback();
        $this->set($key, $computed, $ttl);
        return $computed;
    }

    public function getStats(): array
    {
        $files = glob($this->cacheDir . '/cache_*.json');
        $itemCount = is_array($files) ? count($files) : 0;

        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'apcu_enabled' => $this->isApcuAvailable(),
            'cache_dir' => $this->cacheDir,
            'items_count' => $itemCount,
            'backend' => $this->isApcuAvailable() ? 'apcu+file' : 'file',
        ];
    }
}
