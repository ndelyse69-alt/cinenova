<?php
/**
 * CineNova — .env loader
 * ---------------------------------------------------------------
 * A small hand-rolled .env parser. Composer/Packagist aren't
 * reachable from this environment's network egress allowlist, so
 * a pulled-in package (vlucas/phpdotenv) isn't installable here —
 * this does the same job for the handful of KEY=VALUE lines this
 * project needs, with zero dependencies.
 *
 * Values loaded this way are placed into getenv()/$_ENV so the
 * rest of the app (config.php) can read them exactly as it would
 * with a real dotenv package.
 */
declare(strict_types=1);

function load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Strip matching surrounding quotes, if present.
        if (strlen($value) >= 2 && (
            ($value[0] === '"' && $value[-1] === '"') ||
            ($value[0] === "'" && $value[-1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }
        if ($key === '') {
            continue;
        }
        // Never override a variable the real environment already set
        // (e.g. one exported by the hosting platform) — .env is a
        // local-development fallback, not an authority over it.
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

/** env('KEY', 'default') — the one helper the rest of the app needs. */
function env(string $key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    if (in_array(strtolower($value), ['true', '(true)'], true)) return true;
    if (in_array(strtolower($value), ['false', '(false)'], true)) return false;
    if (in_array(strtolower($value), ['null', '(null)'], true)) return null;
    return $value;
}
