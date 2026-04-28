<?php

if (!function_exists('mojibake_score')) {
    function mojibake_score(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        $pattern = '/(?:\x{00C3}.|\x{00C2}.|\x{00E2}[\x{0080}-\x{00BF}]|\x{00C6}.|\x{FFFD})/u';
        if (!preg_match_all($pattern, $value, $matches)) {
            return 0;
        }

        return count($matches[0]);
    }
}

if (!function_exists('normalize_text_encoding')) {
    function normalize_text_encoding(string $value): string
    {
        $pattern = '/(?:\x{00C3}.|\x{00C2}.|\x{00E2}[\x{0080}-\x{00BF}]|\x{00C6}.|\x{FFFD})/u';
        if ($value === '' || !preg_match($pattern, $value)) {
            return $value;
        }

        $best = $value;
        $bestScore = mojibake_score($value);
        $candidate = $value;

        // Double/triple mojibake usually needs one or more UTF-8 -> Windows-1252 reversals.
        for ($i = 0; $i < 4; $i++) {
            $next = null;
            if (function_exists('mb_convert_encoding')) {
                $next = @mb_convert_encoding($candidate, 'Windows-1252', 'UTF-8');
            } elseif (function_exists('iconv')) {
                $next = @iconv('UTF-8', 'Windows-1252//IGNORE', $candidate);
            }

            if (!is_string($next) || $next === '' || $next === $candidate) {
                break;
            }

            if (function_exists('mb_check_encoding') && !@mb_check_encoding($next, 'UTF-8')) {
                break;
            }

            $score = mojibake_score($next);
            if ($score > $bestScore) {
                break;
            }

            $best = $next;
            $bestScore = $score;
            $candidate = $next;

            if ($bestScore === 0) {
                break;
            }
        }

        return $best;
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars(normalize_text_encoding((string) $value), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('current_path')) {
    function current_path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return rtrim($path, '/') ?: '/';
    }
}

if (!function_exists('base_path_url')) {
    function base_path_url(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($script));

        return rtrim($dir, '/');
    }
}

if (!function_exists('route_url')) {
    function route_url(string $route = ''): string
    {
        $base = base_path_url();

        return $base . ($route !== '' ? '/' . ltrim($route, '/') : '');
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $asset = ''): string
    {
        $assetPath = 'assets/' . ltrim($asset, '/');

        if (defined('AREA')) {
            $area = strtolower((string) AREA);
            $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            $script = rtrim($script, '/');

            if ($script === '') {
                $script = '/';
            }

            if (!str_ends_with($script, '/' . $area)) {
                $assetPath = $area . '/' . $assetPath;
            }
        }

        return route_url($assetPath);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool
    {
        return isset($_SESSION['_token']) && is_string($token) && hash_equals($_SESSION['_token'], $token);
    }
}

if (!function_exists('flash')) {
    function flash(string $key, ?string $value = null): ?string
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        if (!isset($_SESSION['_flash'][$key])) {
            return null;
        }

        $message = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);

        return $message;
    }
}

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        return $_POST[$key] ?? $default;
    }
}
