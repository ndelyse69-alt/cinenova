<?php
/**
 * CineNova API — public catalog (trending / discover / search / genres / countries / curated rows).
 * ---------------------------------------------------------------
 * GET /backend/api/catalog.php?type=trending&media=movie|tv&window=day|week&limit=20
 * GET /backend/api/catalog.php?type=discover&media=movie|tv&genre=ID&country=CODE&page=1&limit=20
 * GET /backend/api/catalog.php?type=now_playing&page=1&limit=20
 * GET /backend/api/catalog.php?type=search&media=movie|tv&q=...&page=1
 * GET /backend/api/catalog.php?type=genres&media=movie|tv
 * GET /backend/api/catalog.php?type=countries
 * GET /backend/api/catalog.php?type=agasobanuye              -> curated rows grouped by genre
 * GET /backend/api/catalog.php?type=hero                     -> home page hero slides
 *
 * Read-only, no auth required. Every item returned includes a
 * "watch_url" the frontend can use directly as a card's href.
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

if (api_resolve_method() !== 'GET') {
    api_error('Method not allowed.', 405);
}

function catalog_decorate(array $items, string $mediaType): array
{
    return array_map(function ($item) use ($mediaType) {
        $item['media_type'] = $item['media_type'] ?? $mediaType;
        $item['title'] = titleOf($item);
        $item['year'] = yearOf($item);
        $item['poster_url'] = getPosterUrl($item['poster_path'] ?? null);
        $item['watch_url'] = watchUrl($item, $item['media_type']);
        return $item;
    }, $items);
}

$type = (string) ($_GET['type'] ?? 'trending');
$media = (string) ($_GET['media'] ?? 'movie');
$media = $media === 'tv' ? 'tv' : 'movie';
$limit = max(1, min(60, (int) ($_GET['limit'] ?? 20)));
$page = max(1, (int) ($_GET['page'] ?? 1));

switch ($type) {
    case 'trending':
        $window = ($_GET['window'] ?? 'week') === 'day' ? 'day' : 'week';
        $result = getTrendingMovies($window, $limit, $page);
        if ($media === 'tv') {
            $result = ['results' => getTrendingTV($window, $limit), 'total_pages' => 1];
        }
        api_json(['success' => true, 'data' => [
            'items' => catalog_decorate($result['results'], $media),
            'total_pages' => $result['total_pages'],
            'page' => $page,
        ]]);
        break;

    case 'now_playing':
        $result = getNowPlaying($page, $limit);
        api_json(['success' => true, 'data' => [
            'items' => catalog_decorate($result['results'], 'movie'),
            'total_pages' => $result['total_pages'],
            'page' => $page,
        ]]);
        break;

    case 'discover':
        $params = [];
        if (!empty($_GET['genre'])) $params['with_genres'] = (string) $_GET['genre'];
        if (!empty($_GET['country'])) $params['with_origin_country'] = (string) $_GET['country'];
        if (!empty($_GET['sort'])) $params['sort_by'] = (string) $_GET['sort'];
        $result = getDiscover($media, $params, $limit, $page);
        api_json(['success' => true, 'data' => [
            'items' => catalog_decorate($result['results'], $media),
            'total_pages' => $result['total_pages'],
            'page' => $page,
        ]]);
        break;

    case 'search':
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q === '') {
            api_json(['success' => true, 'data' => ['items' => [], 'total_pages' => 0, 'page' => $page]]);
        }
        $movieResults = searchTitles('movie', $q, $page);
        $tvResults = searchTitles('tv', $q, $page);
        $items = array_merge(
            catalog_decorate($movieResults['results'], 'movie'),
            catalog_decorate($tvResults['results'], 'tv')
        );
        usort($items, fn($a, $b) => ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0));
        api_json(['success' => true, 'data' => [
            'items' => array_slice($items, 0, $limit),
            'total_pages' => max($movieResults['total_pages'], $tvResults['total_pages']),
            'page' => $page,
        ]]);
        break;

    case 'genres':
        api_json(['success' => true, 'data' => ['genres' => getGenres($media)]]);
        break;

    case 'countries':
        $out = [];
        foreach (getCountryList() as $code => $name) {
            $out[] = ['code' => $code, 'name' => $name];
        }
        api_json(['success' => true, 'data' => ['countries' => $out]]);
        break;

    case 'agasobanuye':
        $grouped = getAgasobanuyeMovies();
        $rows = [];
        foreach ($grouped as $genre => $movies) {
            $rows[] = [
                'genre' => $genre,
                'items' => array_map(function ($m) {
                    $m['watch_url'] = watchUrl(['id' => $m['tmdb_id']], $m['media_type']);
                    $m['poster_url'] = getPosterUrl($m['poster_path'] ?? null);
                    return $m;
                }, $movies),
            ];
        }
        api_json(['success' => true, 'data' => ['rows' => $rows]]);
        break;

    case 'hero':
        $trending = getTrendingMovies('week', 6)['results'];
        api_json(['success' => true, 'data' => ['slides' => catalog_decorate($trending, 'movie')]]);
        break;

    default:
        api_error('Unknown catalog type.', 400);
}
