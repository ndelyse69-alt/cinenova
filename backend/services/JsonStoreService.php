<?php
/**
 * CineNova — JSON data-store service.
 * ---------------------------------------------------------------
 * The catalog/categories/countries side of the admin panel manages
 * its data as flat JSON files under backend/storage (blocked from
 * direct web access — see backend/storage/.htaccess). Every
 * read/write goes through here so file locking is consistent and no
 * endpoint has to touch file I/O directly. (Users/auth data has its
 * own reader in AuthService.php; visits has its own in
 * AnalyticsService.php — both follow the same locking pattern.)
 */
declare(strict_types=1);

define('JSON_STORE_DIR', __DIR__ . '/../storage');

/** Read a JSON data file. Returns $default if missing/empty/unparseable. */
function store_read_json(string $filename, array $default = []): array
{
    $path = JSON_STORE_DIR . '/' . $filename;
    if (!is_file($path)) {
        return $default;
    }
    $fp = fopen($path, 'r');
    if ($fp === false) {
        return $default;
    }
    flock($fp, LOCK_SH);
    $contents = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    $data = json_decode((string) $contents, true);
    return is_array($data) ? $data : $default;
}

/** Write a JSON data file atomically with an exclusive lock. */
function store_write_json(string $filename, array $data): bool
{
    $path = JSON_STORE_DIR . '/' . $filename;
    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return false;
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }
    ftruncate($fp, 0);
    rewind($fp);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ok = $json !== false && fwrite($fp, $json) !== false;
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $ok;
}

/** Next auto-increment id for a list of associative arrays keyed by 'id'. */
function store_next_id(array $rows): int
{
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int) ($row['id'] ?? 0));
    }
    return $max + 1;
}

/** Turn a title into a URL/key-safe slug, e.g. "The Croods!" -> "the-croods". */
function store_slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'item-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

/**
 * Parse the admin's "Label | https://embed-url" textarea format into
 * an ordered ['Label' => 'url', ...] array, skipping blank/malformed
 * lines. Accepts either a full http(s) embed URL or a local
 * "uploads/videos/…" path (what the upload endpoint writes).
 */
function store_parse_episode_lines(string $raw): array
{
    $episodes = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '|') === false) {
            continue;
        }
        [$label, $url] = array_map('trim', explode('|', $line, 2));
        if ($label === '' || $url === '') {
            continue;
        }
        $isValidEmbed = filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url);
        $isLocalUpload = strpos($url, UPLOAD_VIDEO_URL_PREFIX) === 0 && strpos($url, '..') === false;
        if (!$isValidEmbed && !$isLocalUpload) {
            continue;
        }
        $episodes[$label] = $url;
    }
    return $episodes;
}

/** Reverse of store_parse_episode_lines(), for pre-filling the edit form. */
function store_episodes_to_lines(array $episodes): string
{
    $lines = [];
    foreach ($episodes as $label => $url) {
        $lines[] = $label . ' | ' . $url;
    }
    return implode("\n", $lines);
}

/**
 * Deterministic "fake TMDB id" for a title with no real TMDB match,
 * so it can still have a normal watch.html?id=X&type=Y URL without
 * ever colliding with a real TMDB id.
 */
function store_synthetic_id(string $slug): int
{
    return 900000000 + (crc32($slug) % 99999999);
}
