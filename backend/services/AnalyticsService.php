<?php
/**
 * CineNova — Analytics service.
 * ---------------------------------------------------------------
 * Lightweight, dependency-free visit tracking backing Admin →
 * Analytics and Admin → Visitors. Stores one row per visit in
 * storage/visits.json (rotated to the most recent 5,000 rows so the
 * file can't grow without bound). No cookies/consent banner needed —
 * only an IP hash (never the raw IP), device class, referrer host
 * and requested path are kept.
 */
declare(strict_types=1);

define('ANALYTICS_MAX_ROWS', 5000);

function analytics_track_visit(string $path): void
{
    $rows = store_read_json('visits.json', []);

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = trim(explode(',', $ip)[0]);
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $referrer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

    $rows[] = [
        'id'        => store_next_id($rows),
        'path'      => $path,
        'ip_hash'   => $ip !== '' ? substr(hash('sha256', $ip), 0, 16) : null,
        'device'    => analytics_guess_device($ua),
        'browser'   => analytics_guess_browser($ua),
        'referrer'  => $referrer !== '' ? parse_url($referrer, PHP_URL_HOST) : null,
        'timestamp' => date('c'),
    ];

    if (count($rows) > ANALYTICS_MAX_ROWS) {
        $rows = array_slice($rows, -ANALYTICS_MAX_ROWS);
    }

    store_write_json('visits.json', $rows);
}

function analytics_guess_device(string $ua): string
{
    $ua = strtolower($ua);
    if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) return 'Tablet';
    if (str_contains($ua, 'mobi') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) return 'Mobile';
    if ($ua === '') return 'Unknown';
    return 'Desktop';
}

function analytics_guess_browser(string $ua): string
{
    $ua = strtolower($ua);
    if (str_contains($ua, 'edg/')) return 'Edge';
    if (str_contains($ua, 'chrome/') && !str_contains($ua, 'chromium')) return 'Chrome';
    if (str_contains($ua, 'firefox/')) return 'Firefox';
    if (str_contains($ua, 'safari/') && !str_contains($ua, 'chrome')) return 'Safari';
    if ($ua === '') return 'Unknown';
    return 'Other';
}

function analytics_visits(): array
{
    return store_read_json('visits.json', []);
}

/** Summary stats for the Admin → Analytics dashboard. */
function analytics_summary(int $days = 30): array
{
    $rows = analytics_visits();
    $cutoff = time() - ($days * 86400);

    $byDay = [];
    $byDevice = [];
    $byPath = [];
    $total = 0;

    foreach ($rows as $row) {
        $ts = strtotime((string) ($row['timestamp'] ?? ''));
        if ($ts === false || $ts < $cutoff) {
            continue;
        }
        $total++;
        $day = date('Y-m-d', $ts);
        $byDay[$day] = ($byDay[$day] ?? 0) + 1;

        $device = $row['device'] ?? 'Unknown';
        $byDevice[$device] = ($byDevice[$device] ?? 0) + 1;

        $path = $row['path'] ?? '/';
        $byPath[$path] = ($byPath[$path] ?? 0) + 1;
    }

    ksort($byDay);
    arsort($byPath);

    return [
        'total_visits'  => $total,
        'by_day'        => $byDay,
        'by_device'     => $byDevice,
        'top_pages'     => array_slice($byPath, 0, 10, true),
        'unique_ip_hashes' => count(array_unique(array_filter(array_column($rows, 'ip_hash')))),
    ];
}

/** Paginated raw rows for the Admin → Visitors table (most recent first). */
function analytics_recent_visits(int $limit = 100): array
{
    $rows = array_reverse(analytics_visits());
    return array_slice($rows, 0, $limit);
}
