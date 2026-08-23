<?php

namespace App\Core;

class ResponseBuffer
{
    private bool $minificationEnabled = true;
    private bool $compressionEnabled = true;
    private array $timings = [];
    private bool $started = false;

    public function start(): void
    {
        if (!$this->started) {
            ob_start([$this, 'processBuffer']);
            $this->started = true;
        }
    }

    public function setMinification(bool $enabled): void
    {
        $this->minificationEnabled = $enabled;
    }

    public function setCompression(bool $enabled): void
    {
        $this->compressionEnabled = $enabled;
    }

    public function addTiming(string $name, float $durationMs, string $description = ''): void
    {
        $this->timings[$name] = [
            'name' => $name,
            'duration' => round($durationMs, 2),
            'description' => $description,
        ];
    }

    public function getTimings(): array
    {
        return $this->timings;
    }

    public function processBuffer(string $buffer): string
    {
        // 1. Send Server-Timing header if headers not sent
        if (!headers_sent() && !empty($this->timings)) {
            $parts = [];
            foreach ($this->timings as $metric) {
                $part = $metric['name'];
                if ($metric['description'] !== '') {
                    $part .= ';desc="' . addslashes($metric['description']) . '"';
                }
                $part .= ';dur=' . $metric['duration'];
                $parts[] = $part;
            }
            header('Server-Timing: ' . implode(', ', $parts));
        }

        // 2. Check if output is JSON, AJAX or DEBUG mode where minification should be skipped
        $trimmedBuffer = trim($buffer);
        $isJsonOrAjax = (
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) ||
            (defined('APP_DEBUG') && APP_DEBUG === true) ||
            (str_starts_with($trimmedBuffer, '{') && str_ends_with($trimmedBuffer, '}')) ||
            (str_starts_with($trimmedBuffer, '[') && str_ends_with($trimmedBuffer, ']'))
        );

        // 3. Minification for HTML responses
        if ($this->minificationEnabled && !empty($buffer) && !$isJsonOrAjax) {
            $minified = $this->minifyHtml($buffer);
            if ($minified !== '') {
                $buffer = $minified;
            }
        }

        // 4. Compression
        if ($this->compressionEnabled && !headers_sent() && !empty($buffer)) {
            $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

            if (strpos($acceptEncoding, 'br') !== false && function_exists('brotli_compress')) {
                $compressed = @brotli_compress($buffer);
                if ($compressed !== false) {
                    header('Content-Encoding: br');
                    header('Vary: Accept-Encoding');
                    return $compressed;
                }
            }

            if (strpos($acceptEncoding, 'gzip') !== false && function_exists('gzencode')) {
                $compressed = @gzencode($buffer, 6);
                if ($compressed !== false) {
                    header('Content-Encoding: gzip');
                    header('Vary: Accept-Encoding');
                    return $compressed;
                }
            }
        }

        return $buffer;
    }

    public function minifyHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $placeholders = [];
        $index = 0;

        // Helper for safe regex replacement with fallback
        $safeReplace = function ($pattern, $callback, $subject) {
            $result = preg_replace_callback($pattern, $callback, $subject);
            return $result !== null ? $result : $subject;
        };

        // 1. Extract <textarea> blocks intact
        $html = $safeReplace('/<textarea\b[^>]*>.*?<\/textarea>/is', function ($matches) use (&$placeholders, &$index) {
            $key = "___PLACEHOLDER_TEXTAREA_{$index}___";
            $index++;
            $placeholders[$key] = $matches[0];
            return $key;
        }, $html);

        // 2. Extract <pre> blocks intact
        $html = $safeReplace('/<pre\b[^>]*>.*?<\/pre>/is', function ($matches) use (&$placeholders, &$index) {
            $key = "___PLACEHOLDER_PRE_{$index}___";
            $index++;
            $placeholders[$key] = $matches[0];
            return $key;
        }, $html);

        // 3. Extract <style> blocks (minifying internal CSS)
        $html = $safeReplace('/<style\b[^>]*>(.*?)<\/style>/is', function ($matches) use (&$placeholders, &$index) {
            $minifiedCss = strlen($matches[1]) <= 65536 ? $this->minifyCss($matches[1]) : $matches[1];
            preg_match('/<style\b[^>]*>/i', $matches[0], $tagMatch);
            $fullStyle = ($tagMatch[0] ?? '<style>') . $minifiedCss . '</style>';
            $key = "___PLACEHOLDER_STYLE_{$index}___";
            $index++;
            $placeholders[$key] = $fullStyle;
            return $key;
        }, $html);

        // 4. Extract <script> blocks (minifying internal JS if no src attribute and reasonable size)
        $html = $safeReplace('/<script\b[^>]*>(.*?)<\/script>/is', function ($matches) use (&$placeholders, &$index) {
            $fullTag = $matches[0];
            $content = $matches[1];
            // If external script or large payload (> 64KB), keep intact
            if (stripos($fullTag, 'src=') !== false || strlen($content) > 65536) {
                $key = "___PLACEHOLDER_SCRIPT_{$index}___";
                $index++;
                $placeholders[$key] = $fullTag;
                return $key;
            }

            $minifiedJs = $this->minifyJs($content);
            preg_match('/<script\b[^>]*>/i', $fullTag, $tagMatch);
            $fullScript = ($tagMatch[0] ?? '<script>') . $minifiedJs . '</script>';
            $key = "___PLACEHOLDER_SCRIPT_{$index}___";
            $index++;
            $placeholders[$key] = $fullScript;
            return $key;
        }, $html);

        // 5. Remove HTML comments except IE conditional comments
        $resComments = preg_replace('/<!--(?!\[if\s)[^\]].*?-->/s', '', $html);
        if ($resComments !== null) {
            $html = $resComments;
        }

        // 6. Collapse multiple white space characters outside pre/textarea/style/script
        $resWs1 = preg_replace('/>\s+</', '><', $html);
        if ($resWs1 !== null) {
            $html = $resWs1;
        }

        $resWs2 = preg_replace('/\s+/', ' ', $html);
        if ($resWs2 !== null) {
            $html = $resWs2;
        }

        // 7. Restore placeholders intact
        if (!empty($placeholders)) {
            $html = strtr($html, $placeholders);
        }

        return trim($html);
    }

    public function minifyCss(string $css): string
    {
        if (trim($css) === '') {
            return '';
        }

        // Remove comments
        $css = preg_replace('!/\*.*?\*/!s', '', $css);
        // Remove space around delimiters
        $css = preg_replace('/\s*([{}|:;,])\s*/', '$1', $css);
        // Collapse spaces
        $css = preg_replace('/\s+/', ' ', $css);

        return trim($css);
    }

    public function minifyJs(string $js): string
    {
        if (trim($js) === '') {
            return '';
        }

        // Remove single-line comments (careful with http:// or strings)
        $js = preg_replace('~(?<!:)\/\/(?![^"\'\r\n]*["\']).*$~m', '', $js);
        // Remove multi-line comments
        $js = preg_replace('!/\*.*?\*/!s', '', $js);
        // Remove spaces around operators and delimiters safely (horizontal spaces only to preserve newlines)
        $js = preg_replace('/[ \t]*([{}();,=+\-*\/])[ \t]*/', '$1', $js);
        // Collapse multiple horizontal spaces
        $js = preg_replace('/[ \t]+/', ' ', $js);
        // Trim leading/trailing horizontal whitespace per line
        $js = preg_replace('/^[ \t]+|[ \t]+$/m', '', $js);
        // Collapse multiple blank lines into a single newline
        $js = preg_replace('/[\r\n]+/', "\n", $js);

        return trim($js);
    }

    public function flush(): void
    {
        if ($this->started && ob_get_level() > 0) {
            @ob_flush();
            @flush();
        }
    }

    public function end(): void
    {
        if ($this->started && ob_get_level() > 0) {
            @ob_end_flush();
            $this->started = false;
        }
    }

    public function getClean(): string
    {
        if ($this->started && ob_get_level() > 0) {
            $this->started = false;
            return ob_get_clean() ?: '';
        }
        return '';
    }
}
