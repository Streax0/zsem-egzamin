<?php

namespace App\Core;

class ConfigStore
{
    private string $configPath;
    private array $config = [];
    private array $defaults = [
        'maintenance_mode' => false,
        'minification_enabled' => true,
        'compression_enabled' => true,
        'waf_level' => 'medium',
        'csrf_enforced' => true,
        'session_shield_enabled' => true,
        'rate_limit_per_minute' => 120,
        'asset_version' => '1.0.0',
        'honeypot_enabled' => true,
        'ip_whitelist' => [],
        'ip_blacklist' => [],
    ];
    private string $apcuKey = 'engine_config_store';

    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? __DIR__ . '/../../../data/config/engine_config.json';
        $this->apcuKey = 'engine_config_' . md5($this->configPath);
        $this->load();
    }

    private function isApcuAvailable(): bool
    {
        return function_exists('apcu_fetch') && (bool)ini_get('apc.enabled');
    }

    public function load(): void
    {
        if ($this->isApcuAvailable()) {
            $success = false;
            $cached = apcu_fetch($this->apcuKey, $success);
            if ($success && is_array($cached)) {
                $this->config = array_merge($this->defaults, $cached);
                return;
            }
        }

        if (file_exists($this->configPath)) {
            $content = @file_get_contents($this->configPath);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $this->config = array_merge($this->defaults, $decoded);
                    if ($this->isApcuAvailable()) {
                        apcu_store($this->apcuKey, $this->config);
                    }
                    return;
                }
            }
        }

        $this->config = $this->defaults;
        if ($this->isApcuAvailable()) {
            apcu_store($this->apcuKey, $this->config);
        }
    }

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, $value): bool
    {
        $this->config[$key] = $value;
        return $this->save();
    }

    public function all(): array
    {
        return $this->config;
    }

    public function save(): bool
    {
        $dir = dirname($this->configPath);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return false;
            }
        }

        $json = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $written = @file_put_contents($this->configPath, $json, LOCK_EX);
        if ($written === false) {
            return false;
        }

        if ($this->isApcuAvailable()) {
            apcu_store($this->apcuKey, $this->config);
        }

        return true;
    }

    public function addBannedIp(string $ip): bool
    {
        $list = (array)($this->config['ip_blacklist'] ?? []);
        if (!in_array($ip, $list, true)) {
            $list[] = $ip;
            $this->config['ip_blacklist'] = array_values($list);
            return $this->save();
        }
        return true;
    }

    public function removeBannedIp(string $ip): bool
    {
        $list = (array)($this->config['ip_blacklist'] ?? []);
        $newList = array_filter($list, fn($item) => $item !== $ip);
        $this->config['ip_blacklist'] = array_values($newList);
        return $this->save();
    }

    public function isIpBanned(string $ip): bool
    {
        $list = (array)($this->config['ip_blacklist'] ?? []);
        return in_array($ip, $list, true);
    }

    public function bumpAssetVersion(): string
    {
        $newVer = '1.0.' . time();
        $this->set('asset_version', $newVer);
        return $newVer;
    }

    public function clearCache(): void
    {
        if ($this->isApcuAvailable()) {
            apcu_delete($this->apcuKey);
        }
    }
}
