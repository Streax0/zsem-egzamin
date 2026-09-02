<?php

namespace App\Core;

use App\Security\Waf;
use App\Security\Firewall;

class Engine
{
    private static ?Engine $instance = null;

    private ConfigStore $config;
    private CacheManager $cache;
    private ResponseBuffer $responseBuffer;
    private ?Waf $waf = null;
    private ?Firewall $firewall = null;
    private float $bootTime = 0.0;
    private bool $booted = false;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton Engine.");
    }

    public static function getInstance(): Engine
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function boot(?string $configPath = null, ?string $cacheDir = null): self
    {
        $startTime = microtime(true);
        $this->bootTime = $startTime;

        // 1. ConfigStore
        $this->config = new ConfigStore($configPath);

        // 2. CacheManager
        $this->cache = new CacheManager($cacheDir);

        // 3. ResponseBuffer
        $this->responseBuffer = new ResponseBuffer();
        $this->responseBuffer->setMinification(
            (bool)$this->config->get('minification_enabled', true)
        );
        $this->responseBuffer->setCompression(
            (bool)$this->config->get('compression_enabled', true)
        );
        $this->responseBuffer->start();

        // 4. Maintenance mode check
        $this->checkMaintenanceMode();

        // 5. WAF & Firewall Init & Enforcement
        $this->initWaf();

        // 6. Measure boot duration and add timing
        $durationMs = (microtime(true) - $startTime) * 1000;
        $this->responseBuffer->addTiming('boot', $durationMs, 'App Kernel Boot');

        // 7. Register shutdown telemetry
        register_shutdown_function(function () use ($startTime) {
            if ($this->responseBuffer) {
                $totalExecMs = (microtime(true) - $startTime) * 1000;
                $this->responseBuffer->addTiming('app_exec', $totalExecMs, 'Total App Execution Time');
                $memPeakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
                $this->responseBuffer->addTiming('mem_peak', $memPeakMb, "Peak Memory {$memPeakMb}MB");
            }
        });

        $this->booted = true;
        return $this;
    }

    private function initWaf(): void
    {
        $wafLevel = (string)$this->config->get('waf_level', 'medium');
        $csrfEnforced = (bool)$this->config->get('csrf_enforced', true);

        $this->waf = new Waf();
        $this->firewall = new Firewall($this->waf);
        
        if ($wafLevel === 'disabled' && !$csrfEnforced) {
            return;
        }

        $passed = $this->firewall->protectRequest($wafLevel, $csrfEnforced);
        if (!$passed) {
            $this->renderBlockedPage('WAF lub zapora sieciowa zablokowała przychodzące zapytanie.');
        }
    }

    private function checkMaintenanceMode(): void
    {
        if (!(bool)$this->config->get('maintenance_mode', false)) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_contains($uri, 'login.php') || str_contains($uri, 'actions/login.php') || str_contains($uri, '/admin/')) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $role = (string)($_SESSION['role'] ?? '');
        if (in_array($role, ['admin', 'dyrektor'], true)) {
            return;
        }

        $this->renderMaintenancePage();
    }

    private function renderMaintenancePage(): void
    {
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 300');
        }

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'maintenance',
                'message' => 'Serwis jest obecnie w trakcie prac konserwacyjnych. Spróbuj ponowić za chwilę.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo <<<HTML
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prace konserwacyjne - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%); color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, -apple-system, sans-serif; }
        .card-custom { background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; backdrop-filter: blur(12px); box-shadow: 0 20px 50px rgba(0,0,0,0.5); padding: 3rem 2rem; max-width: 520px; width: 90%; text-align: center; }
        .icon-box { width: 90px; height: 90px; background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 3rem; margin-bottom: 1.5rem; border: 1px solid rgba(96, 165, 250, 0.3); }
    </style>
</head>
<body>
    <div class="card-custom">
        <div class="icon-box"><i class="bi bi-tools"></i></div>
        <h1 class="h3 fw-bold mb-3">Prace Konserwacyjne</h1>
        <p class="text-secondary mb-4">Trwa przerwa techniczna mająca na celu usprawnienie i zabezpieczenie systemu ZSEM Tech. Zapraszamy ponownie za chwilę.</p>
        <a href="login.php" class="btn btn-outline-light rounded-pill px-4 btn-sm">Panel Administratora</a>
    </div>
</body>
</html>
HTML;
        exit;
    }

    private function renderBlockedPage(string $reason): void
    {
        if (!headers_sent()) {
            http_response_code(403);
        }

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'code' => 403,
                'message' => $reason
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ip = function_exists('securityClientIp') ? securityClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        echo <<<HTML
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dostęp Zablokowany - WAF Security</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #31121d 50%, #0f172a 100%); color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, -apple-system, sans-serif; }
        .card-custom { background: rgba(30, 41, 59, 0.85); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 24px; backdrop-filter: blur(12px); box-shadow: 0 20px 50px rgba(0,0,0,0.6); padding: 3rem 2rem; max-width: 540px; width: 90%; text-align: center; }
        .icon-box { width: 90px; height: 90px; background: rgba(239, 68, 68, 0.15); color: #f87171; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 3rem; margin-bottom: 1.5rem; border: 1px solid rgba(248, 113, 113, 0.3); }
        .badge-ip { background: rgba(255, 255, 255, 0.1); color: #cbd5e1; padding: 0.4rem 1rem; border-radius: 50px; font-family: monospace; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="card-custom">
        <div class="icon-box"><i class="bi bi-shield-x"></i></div>
        <h1 class="h3 fw-bold mb-2">Dostęp Zablokowany (403)</h1>
        <p class="text-secondary mb-3">System ochrony WAF przechwycił niedozwoloną operację lub Twój adres IP został tymczasowo zablokowany.</p>
        <div class="mb-4"><span class="badge-ip">IP: {$ip}</span></div>
        <a href="index.php" class="btn btn-outline-light rounded-pill px-4">Powrót do strony głównej</a>
    </div>
</body>
</html>
HTML;
        exit;
    }

    private function isAjaxRequest(): bool
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            return true;
        }
        return false;
    }

    public function getConfig(): ConfigStore
    {
        return $this->config;
    }

    public function getCache(): CacheManager
    {
        return $this->cache;
    }

    public function getResponseBuffer(): ResponseBuffer
    {
        return $this->responseBuffer;
    }

    public function getWaf(): ?Waf
    {
        return $this->waf;
    }

    public function getFirewall(): ?Firewall
    {
        return $this->firewall;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function getBootTime(): float
    {
        return $this->bootTime;
    }

    public function getAssetVersion(): string
    {
        return (string)($this->config ? $this->config->get('asset_version', '1.0.0') : '1.0.0');
    }
}
