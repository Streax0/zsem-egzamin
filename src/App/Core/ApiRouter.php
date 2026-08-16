<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;

class ApiRouter
{
    private array $routes = [];
    private array $namedMiddlewares = [];
    private array $globalMiddlewares = [];
    private string $groupPrefix = '';
    private array $groupMiddlewares = [];
    private ?PDO $pdo = null;
    private float $startTime = 0.0;
    private string $requestId = '';

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
        $this->startTime = microtime(true);
        $this->requestId = function_exists('securityRequestId') ? securityRequestId() : ('req_' . bin2hex(random_bytes(8)));
        $this->registerDefaultMiddlewares();
    }

    private function registerDefaultMiddlewares(): void
    {
        // Auth middleware - requires logged in user
        $this->namedMiddlewares['auth'] = function (array $context, ?callable $next = null) {
            if (!function_exists('isLoggedIn') || !isLoggedIn()) {
                return self::formatResponse(false, null, 'Wymagane logowanie.', 401);
            }
            return $next ? $next($context) : null;
        };

        // Guest or Auth middleware
        $this->namedMiddlewares['guest'] = function (array $context, ?callable $next = null) {
            $isLoggedIn = function_exists('isLoggedIn') && isLoggedIn();
            $isGuest = function_exists('isGuestMode') && isGuestMode();
            if (!$isLoggedIn && !$isGuest) {
                return self::formatResponse(false, null, 'Wymagany dostęp jako gość lub zalogowany użytkownik.', 401);
            }
            return $next ? $next($context) : null;
        };

        // CSRF middleware for mutating operations
        $this->namedMiddlewares['csrf'] = function (array $context, ?callable $next = null) {
            $method = strtoupper($context['method'] ?? 'GET');
            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $token = $context['body']['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
                $valid = function_exists('validateCsrfToken') && validateCsrfToken((string)$token);
                if (!$valid && function_exists('securityValidateRequestCsrf')) {
                    $valid = securityValidateRequestCsrf();
                }
                if (!$valid) {
                    return self::formatResponse(false, null, 'Nieprawidłowy lub wygasły token CSRF.', 403);
                }
            }
            return $next ? $next($context) : null;
        };
    }

    public function registerMiddleware(string $name, callable $middleware): self
    {
        $this->namedMiddlewares[$name] = $middleware;
        return $this;
    }

    public function use(callable|string $middleware): self
    {
        $this->globalMiddlewares[] = $middleware;
        return $this;
    }

    public function addMiddleware(callable|string $middleware): self
    {
        return $this->use($middleware);
    }

    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddlewares = $this->groupMiddlewares;

        $prefix = trim((string)($attributes['prefix'] ?? ''), '/');
        $this->groupPrefix = ($previousPrefix !== '' ? $previousPrefix . '/' : '') . $prefix;

        $middlewares = (array)($attributes['middlewares'] ?? ($attributes['middleware'] ?? []));
        $this->groupMiddlewares = array_merge($previousMiddlewares, $middlewares);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    public function addRoute(string $method, string $path, callable|array $handler, array $middlewares = []): self
    {
        $method = strtoupper(trim($method));
        $cleanPath = trim($path, '/');
        $fullPath = ($this->groupPrefix !== '' ? $this->groupPrefix . '/' : '') . $cleanPath;
        $fullPath = '/' . trim($fullPath, '/');

        $allMiddlewares = array_merge($this->groupMiddlewares, $middlewares);
        $pattern = $this->compilePattern($fullPath);

        $this->routes[$method][] = [
            'raw_path' => $fullPath,
            'pattern' => $pattern,
            'handler' => $handler,
            'middlewares' => $allMiddlewares,
        ];

        return $this;
    }

    public function get(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    public function options(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('OPTIONS', $path, $handler, $middlewares);
    }

    public function any(string $path, callable|array $handler, array $middlewares = []): self
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $m) {
            $this->addRoute($m, $path, $handler, $middlewares);
        }
        return $this;
    }

    private function compilePattern(string $path): string
    {
        $normalized = '/' . trim($path, '/');
        $regex = preg_replace_callback('/\{([a-zA-Z0-9_]+)(?::([^}]+))?\}/', function ($matches) {
            $name = $matches[1];
            $pattern = isset($matches[2]) && $matches[2] !== '' ? $matches[2] : '[^/]+';
            return '(?P<' . $name . '>' . $pattern . ')';
        }, $normalized);

        return '#^' . $regex . '$#u';
    }

    public function dispatch(?string $method = null, ?string $uri = null, ?array $requestBody = null): array
    {
        $isDirectCall = ($method !== null || $uri !== null);
        $method = strtoupper($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = $this->resolveUri($uri);

        $matchedRoute = null;
        $extractedParams = [];
        $allowedMethods = [];

        // Check for matching route
        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $route) {
                if (preg_match($route['pattern'], $uri, $matches)) {
                    $matchedRoute = $route;
                    foreach ($matches as $k => $v) {
                        if (!is_int($k)) {
                            $extractedParams[$k] = $v;
                        }
                    }
                    break;
                }
            }
        }

        // Check if other HTTP methods match this URI (405 Method Not Allowed)
        if ($matchedRoute === null) {
            foreach ($this->routes as $m => $routes) {
                if ($m === $method) continue;
                foreach ($routes as $route) {
                    if (preg_match($route['pattern'], $uri)) {
                        $allowedMethods[] = $m;
                    }
                }
            }

            if (!empty($allowedMethods)) {
                $uniqueAllowed = array_unique($allowedMethods);
                if (!headers_sent()) {
                    header('Allow: ' . implode(', ', $uniqueAllowed));
                }
                $res = self::formatResponse(false, null, 'Method Not Allowed', 405, ['allowed_methods' => $uniqueAllowed]);
                if (!$isDirectCall && PHP_SAPI !== 'cli') {
                    self::sendResponse(false, null, 'Method Not Allowed', 405, ['allowed_methods' => $uniqueAllowed]);
                }
                return $res;
            }

            $res = self::formatResponse(false, null, 'Endpoint Not Found', 404);
            if (!$isDirectCall && PHP_SAPI !== 'cli') {
                self::sendResponse(false, null, 'Endpoint Not Found', 404);
            }
            return $res;
        }

        // Build Request context
        $parsedBody = $requestBody ?? $this->parseRequestBody();
        $request = [
            'method' => $method,
            'uri' => $uri,
            'path' => $uri,
            'params' => $extractedParams,
            'query' => $_GET,
            'body' => $parsedBody,
            'headers' => $this->getRequestHeaders(),
            'pdo' => $this->pdo,
            'request_id' => $this->requestId,
        ];

        // Middleware pipeline
        $pipeline = array_merge($this->globalMiddlewares, $matchedRoute['middlewares']);

        try {
            // Run middlewares in order
            foreach ($pipeline as $mw) {
                $res = $this->runMiddleware($mw, $request);
                if (is_array($res) && isset($res['success']) && $res['success'] === false) {
                    if (!$isDirectCall && PHP_SAPI !== 'cli') {
                        $code = (int)($res['meta']['status_code'] ?? 400);
                        self::sendResponse(false, $res['data'] ?? null, $res['error'] ?? 'Error', $code);
                    }
                    return $res;
                }
            }

            // Execute handler
            $handler = $matchedRoute['handler'];
            $handlerResult = $this->invokeHandler($handler, $extractedParams, $parsedBody, $request);

            if (is_array($handlerResult) && isset($handlerResult['success']) && array_key_exists('data', $handlerResult)) {
                $finalResponse = $handlerResult;
            } else {
                $finalResponse = self::formatResponse(true, $handlerResult, null, 200);
            }

            if (!$isDirectCall && PHP_SAPI !== 'cli') {
                $code = (int)($finalResponse['meta']['status_code'] ?? 200);
                self::sendResponse(
                    (bool)$finalResponse['success'],
                    $finalResponse['data'] ?? null,
                    $finalResponse['error'] ?? null,
                    $code,
                    $finalResponse['meta'] ?? []
                );
            }

            return $finalResponse;
        } catch (Throwable $e) {
            $code = $e->getCode();
            $status = is_int($code) && $code >= 400 && $code < 600 ? $code : 500;
            if ($e instanceof \InvalidArgumentException) $status = 422;

            error_log('[ApiRouter Error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $errorRes = self::formatResponse(false, null, $e->getMessage() ?: 'Internal Server Error', $status);
            if (!$isDirectCall && PHP_SAPI !== 'cli') {
                self::sendResponse(false, null, $e->getMessage() ?: 'Internal Server Error', $status);
            }
            return $errorRes;
        }
    }

    private function runMiddleware(callable|string $middleware, array $request): mixed
    {
        if (is_string($middleware)) {
            // Handle role:xxx syntax
            if (str_starts_with($middleware, 'role:')) {
                $roles = explode(',', substr($middleware, 5));
                $userRole = (string)($_SESSION['role'] ?? 'guest');
                if (!in_array($userRole, $roles, true)) {
                    return self::formatResponse(false, null, 'Brak uprawnień do tego zasobu.', 403);
                }
                return null;
            }

            // Handle throttle:limit,window syntax
            if (str_starts_with($middleware, 'throttle:')) {
                [$limit, $window] = array_map('intval', explode(',', substr($middleware, 9)) + [60, 60]);
                $actorKey = function_exists('securityActorKey') ? securityActorKey() : ($_SERVER['REMOTE_ADDR'] ?? 'guest');
                if (function_exists('securityThrottle')) {
                    $throttled = securityThrottle('api:' . $actorKey, $limit, $window, [
                        'success' => false,
                        'error' => 'Zbyt wiele zapytań. Spróbuj za chwilę.'
                    ]);
                    if ($throttled) {
                        return self::formatResponse(false, null, 'Zbyt wiele zapytań. Spróbuj za chwilę.', 429);
                    }
                }
                return null;
            }

            if (isset($this->namedMiddlewares[$middleware])) {
                $middleware = $this->namedMiddlewares[$middleware];
            }
        }

        if (is_callable($middleware)) {
            return $middleware($request, function ($req) {
                return null;
            });
        }

        return null;
    }

    private function invokeHandler(callable|array $handler, array $params, array $body, array $request): mixed
    {
        if (is_callable($handler)) {
            if ($handler instanceof \Closure || is_string($handler)) {
                $ref = new ReflectionFunction($handler);
                $p = $ref->getParameters();
                if (!empty($p) && in_array($p[0]->getName(), ['req', 'request', 'context'], true)) {
                    return $handler($request, $params, $this->pdo);
                }
            }
            return $handler($params, $body, $this->pdo, $request);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$classOrObj, $methodName] = $handler;
            $instance = is_string($classOrObj) ? new $classOrObj() : $classOrObj;
            $ref = new ReflectionMethod($instance, $methodName);
            $p = $ref->getParameters();
            if (!empty($p) && in_array($p[0]->getName(), ['req', 'request', 'context'], true)) {
                return $instance->$methodName($request, $params, $this->pdo);
            }
            return $instance->$methodName($params, $body, $this->pdo, $request);
        }

        throw new \RuntimeException('Invalid route handler specified.');
    }

    private function resolveUri(?string $uri): string
    {
        if ($uri !== null) {
            $path = parse_url($uri, PHP_URL_PATH) ?? $uri;
        } elseif (!empty($_SERVER['PATH_INFO'])) {
            $path = $_SERVER['PATH_INFO'];
        } elseif (!empty($_GET['route'])) {
            $path = '/' . ltrim((string)$_GET['route'], '/');
        } else {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
            $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
            $path = preg_replace('#^/api(?:/index\.php)?#i', '', $path) ?: '/';
        }

        $trimmed = '/' . trim($path, '/');
        return $trimmed === '/' ? '/' : rtrim($trimmed, '/');
    }

    private function parseRequestBody(): array
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'GET') {
            return [];
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (str_contains(strtolower($contentType), 'application/json')) {
            $raw = @file_get_contents('php://input');
            if ($raw !== false && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return $_POST;
    }

    private function getRequestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders() ?: [];
        }
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    public static function formatResponse(bool $success, mixed $data = null, ?string $error = null, int $statusCode = 200, array $extraMeta = []): array
    {
        $meta = array_merge([
            'status_code' => $statusCode,
            'code' => $statusCode,
            'timestamp' => time(),
            'request_id' => function_exists('securityRequestId') ? securityRequestId() : ('req_' . bin2hex(random_bytes(8))),
        ], $extraMeta);

        return [
            'success' => $success,
            'data' => $data,
            'error' => $error,
            'meta' => $meta,
        ];
    }

    public static function sendResponse(bool $success, mixed $data = null, ?string $error = null, int $statusCode = 200, array $extraMeta = []): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            if (function_exists('securityApplyJsonHeaders')) {
                securityApplyJsonHeaders();
            } else {
                header('Content-Type: application/json; charset=utf-8');
            }
        }

        $payload = self::formatResponse($success, $data, $error, $statusCode, $extraMeta);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function sendSuccess(mixed $data = null, int $statusCode = 200, array $extraMeta = []): void
    {
        self::sendResponse(true, $data, null, $statusCode, $extraMeta);
    }

    public static function sendError(string $message, int $statusCode = 400, mixed $data = null, array $extraMeta = []): void
    {
        self::sendResponse(false, $data, $message, $statusCode, $extraMeta);
    }
}
