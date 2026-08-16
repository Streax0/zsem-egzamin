<?php
declare(strict_types=1);

namespace App\Core;

class CacheManager
{
    private string $cacheDir;
    private int $hits = 0;
    private int $misses = 0;
    private string $apcuPrefix = 'app_cache_';
    private array $tagVersionsMemory = [];
    private string $tagMapFile;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = rtrim($cacheDir ?? __DIR__ . '/../../../data/cache', '/\\');
        $this->ensureDirectoryExists($this->cacheDir);
        $this->ensureDirectoryExists($this->cacheDir . '/assets');
        $this->ensureDirectoryExists($this->cacheDir . '/tags');
        $this->apcuPrefix = 'app_cache_' . md5($this->cacheDir) . '_';
        $this->tagMapFile = $this->cacheDir . '/tag_index.json';
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

    public function getTagVersion(string $tag): string
    {
        $tag = trim($tag);
        if (isset($this->tagVersionsMemory[$tag])) {
            return $this->tagVersionsMemory[$tag];
        }

        $tagKey = '_tag_' . md5($tag);

        // 1. Try APCu
        if ($this->isApcuAvailable()) {
            $success = false;
            $ver = apcu_fetch($this->apcuPrefix . $tagKey, $success);
            if ($success && is_string($ver)) {
                $this->tagVersionsMemory[$tag] = $ver;
                return $ver;
            }
        }

        // 2. Try File
        $tagFile = $this->cacheDir . '/tags/tag_' . md5($tag) . '.json';
        if (file_exists($tagFile)) {
            $content = @file_get_contents($tagFile);
            if ($content !== false && $content !== '') {
                $data = json_decode($content, true);
                if (is_array($data) && isset($data['version'])) {
                    $ver = (string)$data['version'];
                    if ($this->isApcuAvailable()) {
                        apcu_store($this->apcuPrefix . $tagKey, $ver, 0);
                    }
                    $this->tagVersionsMemory[$tag] = $ver;
                    return $ver;
                }
            }
        }

        // 3. Initialize default version
        $initialVer = '1.0';
        $this->setTagVersion($tag, $initialVer);
        return $initialVer;
    }

    private function setTagVersion(string $tag, string $version): void
    {
        $tag = trim($tag);
        $this->tagVersionsMemory[$tag] = $version;
        $tagKey = '_tag_' . md5($tag);

        if ($this->isApcuAvailable()) {
            apcu_store($this->apcuPrefix . $tagKey, $version, 0);
        }

        $tagsDir = $this->cacheDir . '/tags';
        $this->ensureDirectoryExists($tagsDir);
        $tagFile = $tagsDir . '/tag_' . md5($tag) . '.json';

        $payload = json_encode([
            'tag' => $tag,
            'version' => $version,
            'updated_at' => microtime(true),
        ], JSON_UNESCAPED_SLASHES);

        @file_put_contents($tagFile, $payload, LOCK_EX);
    }

    private function loadTagMap(): array
    {
        if (!file_exists($this->tagMapFile)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($this->tagMapFile), true);
        return is_array($data) ? $data : [];
    }

    private function saveTagMap(array $map): void
    {
        @file_put_contents($this->tagMapFile, json_encode($map, JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public function invalidateTags(array $tags): int
    {
        $tagMap = $this->loadTagMap();
        $keysToPurge = [];
        $tagsBumped = 0;

        foreach ($tags as $tag) {
            $tag = trim((string)$tag);
            if ($tag === '') {
                continue;
            }

            $newVersion = sprintf('%.6f_%s', microtime(true), bin2hex(random_bytes(3)));
            $this->setTagVersion($tag, $newVersion);
            $tagsBumped++;

            if (isset($tagMap[$tag]) && is_array($tagMap[$tag])) {
                foreach ($tagMap[$tag] as $key) {
                    $keysToPurge[$key] = true;
                }
                unset($tagMap[$tag]);
            }
        }

        $purgedCount = 0;
        foreach (array_keys($keysToPurge) as $key) {
            if ($this->delete((string)$key)) {
                $purgedCount++;
            }
        }

        $this->saveTagMap($tagMap);

        return $purgedCount > 0 ? $purgedCount : $tagsBumped;
    }

    public function invalidateTag(string $tag): bool
    {
        return $this->invalidateTags([$tag]) > 0;
    }

    private function getTagVersionsMap(array $tags): array
    {
        $map = [];
        foreach ($tags as $tag) {
            $tag = trim((string)$tag);
            if ($tag !== '') {
                $map[$tag] = $this->getTagVersion($tag);
            }
        }
        return $map;
    }

    private function validateTagVersions(array $tagsMap): bool
    {
        foreach ($tagsMap as $tag => $savedVer) {
            if ($this->getTagVersion((string)$tag) !== (string)$savedVer) {
                return false;
            }
        }
        return true;
    }

    public function get(string $key, $default = null)
    {
        // 1. Try APCu
        if ($this->isApcuAvailable()) {
            $success = false;
            $cached = apcu_fetch($this->apcuPrefix . $key, $success);
            if ($success) {
                if (is_array($cached) && array_key_exists('__c_val', $cached) && array_key_exists('__c_tags', $cached)) {
                    if ($this->validateTagVersions($cached['__c_tags'])) {
                        $this->hits++;
                        return $cached['__c_val'];
                    } else {
                        // Stale tag version - invalidate
                        apcu_delete($this->apcuPrefix . $key);
                        $this->deleteFile($key);
                        $this->misses++;
                        return $default;
                    }
                }
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

        // Check expiration
        if ($payload['expires_at'] > 0 && time() > $payload['expires_at']) {
            @unlink($filePath);
            if ($this->isApcuAvailable()) {
                apcu_delete($this->apcuPrefix . $key);
            }
            $this->misses++;
            return $default;
        }

        // Check tag versions
        if (!empty($payload['tags']) && is_array($payload['tags'])) {
            if (!$this->validateTagVersions($payload['tags'])) {
                @unlink($filePath);
                if ($this->isApcuAvailable()) {
                    apcu_delete($this->apcuPrefix . $key);
                }
                $this->misses++;
                return $default;
            }
        }

        $value = @unserialize($payload['value'], ['allowed_classes' => true]);
        if ($value === false && $payload['value'] !== serialize(false)) {
            $this->misses++;
            return $default;
        }

        // Repopulate APCu if available
        if ($this->isApcuAvailable()) {
            $ttl = $payload['expires_at'] > 0 ? max(1, $payload['expires_at'] - time()) : 0;
            $tagsMap = $payload['tags'] ?? [];
            if (!empty($tagsMap)) {
                apcu_store($this->apcuPrefix . $key, [
                    '__c_val' => $value,
                    '__c_tags' => $tagsMap,
                ], $ttl);
            } else {
                apcu_store($this->apcuPrefix . $key, $value, $ttl);
            }
        }

        $this->hits++;
        return $value;
    }

    public function set(string $key, $value, int $ttl = 3600, array $tags = []): bool
    {
        $expiresAt = $ttl > 0 ? time() + $ttl : 0;
        $tagsMap = $this->getTagVersionsMap($tags);

        // Update tag map index if tags present
        if (!empty($tags)) {
            $tagMap = $this->loadTagMap();
            foreach ($tags as $tag) {
                $tag = trim((string)$tag);
                if ($tag === '') continue;
                if (!isset($tagMap[$tag])) {
                    $tagMap[$tag] = [];
                }
                if (!in_array($key, $tagMap[$tag], true)) {
                    $tagMap[$tag][] = $key;
                }
            }
            $this->saveTagMap($tagMap);
        }

        // Save to APCu if available
        if ($this->isApcuAvailable()) {
            if (!empty($tagsMap)) {
                apcu_store($this->apcuPrefix . $key, [
                    '__c_val' => $value,
                    '__c_tags' => $tagsMap,
                ], $ttl);
            } else {
                apcu_store($this->apcuPrefix . $key, $value, $ttl);
            }
        }

        // Save to File
        $filePath = $this->getFilePath($key);
        $payload = [
            'key' => $key,
            'expires_at' => $expiresAt,
            'tags' => $tagsMap,
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

    public function setWithTags(string $key, $value, int $ttl = 3600, array $tags = []): bool
    {
        return $this->set($key, $value, $ttl, $tags);
    }

    private function deleteFile(string $key): bool
    {
        $filePath = $this->getFilePath($key);
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }
        return true;
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
        $this->tagVersionsMemory = [];

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
            if (file_exists($this->tagMapFile)) {
                @unlink($this->tagMapFile);
            }
        }

        if (in_array($type, ['tags', 'all'], true)) {
            $tagFiles = glob($this->cacheDir . '/tags/tag_*.json');
            if (is_array($tagFiles)) {
                foreach ($tagFiles as $file) {
                    if (is_file($file)) {
                        @unlink($file);
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

    public function remember(string $key, int $ttl, callable $callback, array $tags = [])
    {
        $value = $this->get($key, null);
        if ($value !== null) {
            return $value;
        }

        $computed = $callback();
        $this->set($key, $computed, $ttl, $tags);
        return $computed;
    }

    public function getStats(): array
    {
        $files = glob($this->cacheDir . '/cache_*.json');
        $itemCount = is_array($files) ? count($files) : 0;
        $tagFiles = glob($this->cacheDir . '/tags/tag_*.json');
        $tagCount = is_array($tagFiles) ? count($tagFiles) : 0;

        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'apcu_enabled' => $this->isApcuAvailable(),
            'cache_dir' => $this->cacheDir,
            'items_count' => $itemCount,
            'tags_count' => $tagCount,
            'backend' => $this->isApcuAvailable() ? 'apcu+file' : 'file',
        ];
    }
}
