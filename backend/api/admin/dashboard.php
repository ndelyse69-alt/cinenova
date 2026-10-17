<?php
/**
 * CineNova Admin API — dashboard, analytics, visitors & system info.
 * ---------------------------------------------------------------
 * GET /backend/api/admin/dashboard.php?view=summary    -> Admin → Dashboard cards
 * GET /backend/api/admin/dashboard.php?view=analytics   -> Admin → Analytics charts
 * GET /backend/api/admin/dashboard.php?view=visitors&limit=100 -> Admin → Visitors table
 * GET /backend/api/admin/dashboard.php?view=system      -> Admin → System info
 *
 * Grouped into one endpoint (rather than 4 near-empty files) since
 * all four views are read-only admin-only reporting drawn from the
 * same underlying data (catalog, users, visits) with no independent
 * write actions of their own.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../_helpers.php';

api_require_admin();

if (api_resolve_method() !== 'GET') {
    api_error('Method not allowed.', 405);
}

$view = (string) ($_GET['view'] ?? 'summary');

switch ($view) {
    case 'summary':
        $catalog = store_read_json('movies-catalog.json', []);
        $users = auth_read_users();
        $categories = store_read_json('categories.json', []);
        $countries = store_read_json('countries.json', []);
        $visitsToday = 0;
        $today = date('Y-m-d');
        foreach (analytics_visits() as $v) {
            if (str_starts_with((string) ($v['timestamp'] ?? ''), $today)) {
                $visitsToday++;
            }
        }

        $movieCount = 0;
        $seriesCount = 0;
        foreach ($catalog as $entry) {
            if (($entry['media_type'] ?? 'movie') === 'tv') $seriesCount++; else $movieCount++;
        }

        api_json(['success' => true, 'data' => [
            'total_titles' => count($catalog),
            'movie_count' => $movieCount,
            'series_count' => $seriesCount,
            'total_users' => count($users),
            'total_categories' => count($categories),
            'total_countries' => count($countries),
            'visits_today' => $visitsToday,
            'visits_total' => count(analytics_visits()),
            'recent_titles' => array_slice(array_reverse(array_map(
                fn($slug, $e) => ['slug' => $slug, 'title' => $e['title'] ?? 'Untitled', 'media_type' => $e['media_type'] ?? 'movie'],
                array_keys($catalog), $catalog
            )), 0, 8),
        ]]);
        break;

    case 'analytics':
        $days = max(1, min(365, (int) ($_GET['days'] ?? 30)));
        api_json(['success' => true, 'data' => analytics_summary($days)]);
        break;

    case 'visitors':
        $limit = max(1, min(500, (int) ($_GET['limit'] ?? 100)));
        api_json(['success' => true, 'data' => ['visits' => analytics_recent_visits($limit)]]);
        break;

    case 'system':
        api_json(['success' => true, 'data' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'os' => PHP_OS,
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'curl_enabled' => function_exists('curl_init'),
            'ffprobe_available' => (bool) @shell_exec('command -v ffprobe'),
            'storage_writable' => is_writable(JSON_STORE_DIR),
            'uploads_writable' => is_writable(UPLOAD_ROOT_DIR),
            'db_connected' => (function () {
                try { Database::pdo(); return true; } catch (Throwable $e) { return false; }
            })(),
            'tmdb_api_key_set' => TMDB_API_KEY !== '',
        ]]);
        break;

    default:
        api_error('Unknown view.', 400);
}
