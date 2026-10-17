<?php
/**
 * CineNova — TMDB integration service.
 * ---------------------------------------------------------------
 * Every piece of logic that talks to (or fills in for) the TMDB API:
 * the HTTP fetch + file cache, trending/discover/search, poster/
 * backdrop URL builders, and the offline placeholder generators.
 * This is the ONLY place TMDB_API_KEY is read — no frontend JS file
 * ever sees it, and no other backend file re-implements a fetch.
 *
 * Moved out of the old monolithic config.php into its own service so
 * backend/api/* endpoints (movies, series/tv, watch, search) can each
 * require just this, without pulling in unrelated admin/CRUD logic.
 */
declare(strict_types=1);

define('TMDB_API_KEY', (string) env('TMDB_API_KEY', ''));
define('TMDB_BASE_URL', (string) env('TMDB_BASE_URL', 'https://api.themoviedb.org/3'));
define('TMDB_IMAGE_BASE', (string) env('TMDB_IMAGE_BASE', 'https://image.tmdb.org/t/p/w342'));
define('VIDSRC_BASE_URL', (string) env('VIDSRC_BASE_URL', 'https://vidsrc.tw/embed'));
define('VIDSRC_FALLBACK_BASE_URL', (string) env('VIDSRC_FALLBACK_BASE_URL', 'https://vidsrc.ir/embed'));

/**
 * TMDB response cache directory. File-based (no Redis/Memcached
 * dependency to install), one file per unique request URL, each
 * holding its own expiry — a real cross-request cache, unlike a
 * function-static array which only survives a single page load.
 */
define('TMDB_CACHE_DIR', __DIR__ . '/../storage/cache');
define('TMDB_CACHE_TTL', 900); // 15 minutes

function tmdb_cache_get(string $key): ?array
{
    $path = TMDB_CACHE_DIR . '/' . $key . '.json';
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    $entry = $raw !== false ? json_decode($raw, true) : null;
    if (!is_array($entry) || !isset($entry['expires'], $entry['data'])) {
        return null;
    }
    if ($entry['expires'] < time()) {
        return null;
    }
    return $entry['data'];
}

function tmdb_cache_set(string $key, array $data): void
{
    if (!is_dir(TMDB_CACHE_DIR)) {
        @mkdir(TMDB_CACHE_DIR, 0755, true);
    }
    $path = TMDB_CACHE_DIR . '/' . $key . '.json';
    $payload = json_encode(['expires' => time() + TMDB_CACHE_TTL, 'data' => $data]);
    if ($payload !== false) {
        @file_put_contents($path, $payload, LOCK_EX);
    }
}

function fetchFromTMDB(string $endpoint, array $params = []): ?array
{
    static $requestCache = [];

    $params['api_key'] = TMDB_API_KEY;
    $url = TMDB_BASE_URL . $endpoint . '?' . http_build_query($params);
    $cacheKey = md5($url);

    if (isset($requestCache[$cacheKey])) {
        return $requestCache[$cacheKey];
    }

    $cached = tmdb_cache_get($cacheKey);
    if ($cached !== null) {
        $requestCache[$cacheKey] = $cached;
        return $cached;
    }

    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_ENCODING, '');

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('TMDB API Error: ' . $error);
        return null;
    }

    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded)) {
        return null;
    }

    $requestCache[$cacheKey] = $decoded;
    tmdb_cache_set($cacheKey, $decoded);
    return $decoded;
}

function getTrendingMovies(string $timeWindow = 'week', int $limit = 20, int $page = 1): array
{
    $data = fetchFromTMDB("/trending/movie/{$timeWindow}", ['language' => 'en-US', 'page' => $page]);
    if (!$data || empty($data['results'])) {
        return ['results' => get_placeholder_titles($limit, 'movie'), 'total_pages' => 1];
    }
    return ['results' => array_slice($data['results'], 0, $limit), 'total_pages' => (int) ($data['total_pages'] ?? 1)];
}

function getTrendingTV(string $timeWindow = 'week', int $limit = 20): array
{
    $data = fetchFromTMDB("/trending/tv/{$timeWindow}", ['language' => 'en-US']);
    if (!$data || empty($data['results'])) {
        return get_placeholder_titles($limit, 'tv');
    }
    return array_slice($data['results'], 0, $limit);
}

function getDiscover(string $mediaType, array $extraParams, int $limit = 20, int $page = 1): array
{
    $params = array_merge(['language' => 'en-US', 'page' => $page, 'sort_by' => 'popularity.desc'], $extraParams);
    $data = fetchFromTMDB("/discover/{$mediaType}", $params);
    if (!$data || empty($data['results'])) {
        // Never return generic placeholder titles for a filtered country query.
        // This keeps country pages (e.g. China/CN) limited to that country only.
        if (!empty($extraParams['with_origin_country'])) {
            return ['results' => [], 'total_pages' => 0];
        }
        return ['results' => get_placeholder_titles($limit, $mediaType), 'total_pages' => 1];
    }
    return ['results' => array_slice($data['results'], 0, $limit), 'total_pages' => (int) ($data['total_pages'] ?? 1)];
}

function searchTitles(string $mediaType, string $query, int $page = 1): array
{
    $data = fetchFromTMDB("/search/{$mediaType}", ['query' => $query, 'language' => 'en-US', 'page' => $page]);
    if (!$data || !isset($data['results'])) {
        return ['results' => [], 'total_pages' => 0];
    }
    return ['results' => $data['results'], 'total_pages' => (int) ($data['total_pages'] ?? 1)];
}

function getGenres(string $mediaType): array
{
    $data = fetchFromTMDB("/genre/{$mediaType}/list", ['language' => 'en-US']);
    if ($data && !empty($data['genres'])) {
        return $data['genres'];
    }

    $fallback = [
        'movie' => [
            ['id' => 12, 'name' => 'Adventure'], ['id' => 16, 'name' => 'Animation'],
            ['id' => 35, 'name' => 'Comedy'], ['id' => 80, 'name' => 'Crime'],
            ['id' => 99, 'name' => 'Documentary'], ['id' => 18, 'name' => 'Drama'],
            ['id' => 10751, 'name' => 'Family'], ['id' => 14, 'name' => 'Fantasy'],
            ['id' => 36, 'name' => 'History'], ['id' => 27, 'name' => 'Horror'],
            ['id' => 9648, 'name' => 'Mystery'], ['id' => 10749, 'name' => 'Romance'],
            ['id' => 878, 'name' => 'Science Fiction'], ['id' => 53, 'name' => 'Thriller'],
            ['id' => 10752, 'name' => 'War'], ['id' => 37, 'name' => 'Western'],
        ],
        'tv' => [
            ['id' => 10759, 'name' => 'Action & Adventure'], ['id' => 16, 'name' => 'Animation'],
            ['id' => 35, 'name' => 'Comedy'], ['id' => 80, 'name' => 'Crime'],
            ['id' => 99, 'name' => 'Documentary'], ['id' => 18, 'name' => 'Drama'],
            ['id' => 10751, 'name' => 'Family'], ['id' => 9648, 'name' => 'Mystery'],
            ['id' => 10765, 'name' => 'Sci-Fi & Fantasy'], ['id' => 10768, 'name' => 'War & Politics'],
            ['id' => 37, 'name' => 'Western'],
        ],
    ];

    return $fallback[$mediaType] ?? $fallback['movie'];
}

function getCountryList(): array
{
    return [
        'RW' => 'Rwanda', 'UG' => 'Uganda', 'KE' => 'Kenya', 'TZ' => 'Tanzania', 'BI' => 'Burundi',
        'NG' => 'Nigeria', 'GH' => 'Ghana', 'ZA' => 'South Africa', 'EG' => 'Egypt', 'ET' => 'Ethiopia',
        'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'IE' => 'Ireland',
        'FR' => 'France', 'DE' => 'Germany', 'ES' => 'Spain', 'IT' => 'Italy', 'PT' => 'Portugal',
        'NL' => 'Netherlands', 'BE' => 'Belgium', 'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark',
        'FI' => 'Finland', 'PL' => 'Poland', 'RU' => 'Russia', 'TR' => 'Turkey',
        'IN' => 'India', 'PK' => 'Pakistan', 'BD' => 'Bangladesh', 'CN' => 'China', 'JP' => 'Japan',
        'KR' => 'South Korea', 'TH' => 'Thailand', 'ID' => 'Indonesia', 'PH' => 'Philippines',
        'AU' => 'Australia', 'NZ' => 'New Zealand', 'BR' => 'Brazil', 'AR' => 'Argentina',
        'MX' => 'Mexico', 'CO' => 'Colombia', 'CL' => 'Chile',
    ];
}

function vidsrc_embed_url(int $tmdbId, string $mediaType, ?int $season = null, ?int $episode = null): string
{
    $base = rtrim(VIDSRC_BASE_URL, '/');
    $mediaType = $mediaType === 'tv' ? 'tv' : 'movie';

    if ($mediaType === 'tv' && $season !== null && $episode !== null) {
        return "{$base}/tv/{$tmdbId}/{$season}/{$episode}";
    }
    return "{$base}/{$mediaType}/{$tmdbId}";
}

function vidsrc_embed_urls(int $tmdbId, string $mediaType, ?int $season = null, ?int $episode = null): array
{
    $bases = [VIDSRC_BASE_URL, VIDSRC_FALLBACK_BASE_URL];
    $urls = [];
    foreach ($bases as $base) {
        $base = rtrim((string) $base, '/');
        if ($base === '') continue;
        if ($mediaType === 'tv' && $season !== null && $episode !== null) {
            $urls[] = "{$base}/tv/{$tmdbId}/{$season}/{$episode}";
        } else {
            $urls[] = "{$base}/movie/{$tmdbId}";
        }
    }
    return array_values(array_unique($urls));
}

function getPosterUrl(?string $posterPath, string $size = 'w342'): string
{
    if (empty($posterPath)) {
        return 'https://via.placeholder.com/342x513/14131a/ff6a1a?text=' . rawurlencode(SITE_NAME);
    }
    if (preg_match('#^https?://#i', $posterPath) || str_contains($posterPath, 'uploads/')) {
        return $posterPath;
    }
    return "https://image.tmdb.org/t/p/{$size}{$posterPath}";
}

function getBackdropUrl(?string $backdropPath, string $size = 'original'): string
{
    if (empty($backdropPath)) {
        return '';
    }
    return "https://image.tmdb.org/t/p/{$size}{$backdropPath}";
}

function titleOf(array $item): string
{
    return $item['title'] ?? $item['name'] ?? 'Untitled';
}

function yearOf(array $item): string
{
    $date = $item['release_date'] ?? $item['first_air_date'] ?? '';
    return $date ? (string) date('Y', strtotime($date)) : 'TBA';
}

function get_placeholder_titles(int $count, string $mediaType = 'movie'): array
{
    $titles = [
        'Midnight Frequency', 'Glass Horizon', 'The Last Reel', 'Ember & Static',
        'Nocturne City', 'Paper Constellations', 'The Quiet Machine', 'Amber Road',
        'Signal Lost', 'Velvet Skyline', 'Afterglow', 'The Long Take',
    ];
    $langs = ['en', 'fr', 'ko', 'ja', 'es'];

    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $name = $titles[$i % count($titles)];
        $row = [
            'id'                => $i + 1,
            'poster_path'       => null,
            'backdrop_path'     => null,
            'vote_average'      => round(6.5 + (($i * 37) % 35) / 10, 1),
            'overview'          => 'Details for this title will appear here once the TMDB connection is live.',
            'original_language' => $langs[$i % count($langs)],
        ];
        if ($mediaType === 'tv') {
            $row['name'] = $name;
            $row['first_air_date'] = '';
        } else {
            $row['title'] = $name;
            $row['release_date'] = '';
        }
        $out[] = $row;
    }
    return $out;
}

function getAuthPosterColumns(int $columns = 3, int $perColumn = 6): array
{
    $movies = getTrendingMovies('week', $columns * $perColumn)['results'] ?? [];
    $urls = [];
    foreach ($movies as $movie) {
        if (!empty($movie['poster_path'])) {
            $urls[] = getPosterUrl($movie['poster_path'], 'w342');
        }
    }
    if (empty($urls)) {
        return [];
    }
    $cols = array_fill(0, $columns, []);
    $total = $columns * $perColumn;
    for ($i = 0; $i < $total; $i++) {
        $cols[$i % $columns][] = $urls[$i % count($urls)];
    }
    return $cols;
}

function watchUrl(array $item, string $mediaType = 'movie'): string
{
    $id = (int) ($item['id'] ?? $item['tmdb_id'] ?? 0);
    $type = $mediaType === 'tv' ? 'tv' : 'movie';
    return 'watch.html?id=' . $id . '&type=' . $type;
}

function getNowPlaying(int $page = 1, int $limit = 20): array
{
    $data = fetchFromTMDB('/movie/now_playing', ['language' => 'en-US', 'page' => $page, 'region' => 'US']);
    if ($data && !empty($data['results'])) {
        return [
            'results'     => array_slice($data['results'], 0, $limit),
            'total_pages' => (int) ($data['total_pages'] ?? 1),
        ];
    }
    return ['results' => get_placeholder_titles($limit, 'movie'), 'total_pages' => 1];
}

function getTitleDetails(int $id, string $mediaType): array
{
    $mediaType = $mediaType === 'tv' ? 'tv' : 'movie';

    $data = fetchFromTMDB("/{$mediaType}/{$id}", [
        'language'           => 'en-US',
        'append_to_response' => 'credits,videos,similar',
    ]);

    if ($data && !empty($data['id'])) {
        return $data;
    }

    return get_placeholder_detail($id, $mediaType);
}

function getTrailerKey(array $details): ?string
{
    $videos = $details['videos']['results'] ?? [];
    foreach ($videos as $video) {
        if (($video['site'] ?? '') === 'YouTube' && ($video['type'] ?? '') === 'Trailer') {
            return $video['key'];
        }
    }
    foreach ($videos as $video) {
        if (($video['site'] ?? '') === 'YouTube') {
            return $video['key'];
        }
    }
    return null;
}

function getCast(array $details, int $limit = 10): array
{
    $cast = $details['credits']['cast'] ?? [];
    return array_slice($cast, 0, $limit);
}

function getSimilarTitles(array $details, int $limit = 12): array
{
    $similar = $details['similar']['results'] ?? [];
    if (!empty($similar)) {
        return array_slice($similar, 0, $limit);
    }
    return get_placeholder_titles($limit, $details['media_type'] ?? 'movie');
}

function getRecommendedTitles(array $details, string $mediaType, int $limit = 12): array
{
    $mediaType = $mediaType === 'tv' ? 'tv' : 'movie';
    $currentId = (int) ($details['id'] ?? 0);
    $merged = [];
    $seen = [$currentId => true];

    // TMDB recommendations are a strong first signal because they use
    // TMDB's own recommendation model for the title.
    $recommended = fetchFromTMDB("/{$mediaType}/{$currentId}/recommendations", [
        'language' => 'en-US',
        'page' => 1,
    ]);
    foreach (($recommended['results'] ?? []) as $item) {
        $id = (int) ($item['id'] ?? 0);
        if ($id > 0 && empty($seen[$id])) {
            $seen[$id] = true;
            $merged[] = $item;
        }
        if (count($merged) >= $limit) break;
    }

    // Add titles sharing the same leading cast. This makes the row feel
    // character/cast-aware rather than relying on genre alone.
    $castIds = [];
    foreach (($details['credits']['cast'] ?? []) as $member) {
        $castId = (int) ($member['id'] ?? 0);
        if ($castId > 0) $castIds[] = $castId;
        if (count($castIds) >= 3) break;
    }
    if ($castIds) {
        if ($mediaType === 'movie') {
            $castResults = fetchFromTMDB('/discover/movie', [
                'language' => 'en-US',
                'page' => 1,
                'sort_by' => 'popularity.desc',
                'with_cast' => implode('|', $castIds),
            ]);
            foreach (($castResults['results'] ?? []) as $item) {
                $id = (int) ($item['id'] ?? 0);
                if ($id > 0 && empty($seen[$id])) {
                    $seen[$id] = true;
                    $merged[] = $item;
                }
                if (count($merged) >= $limit) break;
            }
        } else {
            // TMDB's TV discover reference does not expose the movie-style
            // with_cast filter, so use each lead actor's combined credits
            // and keep only TV results.
            foreach ($castIds as $personId) {
                $credits = fetchFromTMDB("/person/{$personId}/combined_credits", ['language' => 'en-US']);
                foreach (($credits['cast'] ?? []) as $item) {
                    if (($item['media_type'] ?? '') !== 'tv') continue;
                    $id = (int) ($item['id'] ?? 0);
                    if ($id > 0 && empty($seen[$id])) {
                        $seen[$id] = true;
                        $merged[] = $item;
                    }
                    if (count($merged) >= $limit) break 2;
                }
            }
        }
    }

    if (count($merged) < $limit) {
        foreach (getSimilarTitles($details, $limit) as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id > 0 && empty($seen[$id])) {
                $seen[$id] = true;
                $merged[] = $item;
            }
            if (count($merged) >= $limit) break;
        }
    }

    return array_slice($merged, 0, $limit);
}

function getProfileUrl(?string $path): ?string
{
    if (empty($path)) {
        return null;
    }
    return TMDB_IMAGE_BASE . $path;
}

function formatRuntime(int $minutes): string
{
    if ($minutes <= 0) {
        return '';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
}

function safe_excerpt(string $text, int $length): string
{
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $length, '…');
    }
    return strlen($text) > $length ? substr($text, 0, $length) . '…' : $text;
}

function get_placeholder_detail(int $id, string $mediaType): array
{
    $titles = get_placeholder_titles(1, $mediaType)[0];

    $fakeCast = [
        ['name' => 'Jordan Blake', 'character' => 'Lead Role', 'profile_path' => null],
        ['name' => 'Casey Morgan', 'character' => 'Supporting Role', 'profile_path' => null],
        ['name' => 'Riley Chen', 'character' => 'Supporting Role', 'profile_path' => null],
        ['name' => 'Avery Nakamura', 'character' => 'Supporting Role', 'profile_path' => null],
        ['name' => 'Sam Okafor', 'character' => 'Supporting Role', 'profile_path' => null],
        ['name' => 'Dana Petrov', 'character' => 'Supporting Role', 'profile_path' => null],
    ];

    return array_merge($titles, [
        'id'                    => $id,
        'genres'                => [['id' => 0, 'name' => 'Drama'], ['id' => 1, 'name' => 'Adventure']],
        'runtime'               => $mediaType === 'movie' ? 118 : null,
        'episode_run_time'      => [42],
        'tagline'               => 'Details for this title will appear here once the TMDB connection is live.',
        'status'                => 'Released',
        'original_language'     => 'en',
        'budget'                => 0,
        'revenue'               => 0,
        'number_of_seasons'     => 1,
        'number_of_episodes'    => 6,
        'seasons'               => [['season_number' => 1, 'name' => 'Season 1']],
        'belongs_to_collection' => null,
        'credits'               => ['cast' => $fakeCast],
        'videos'                => ['results' => []],
        'similar'               => ['results' => get_placeholder_titles(12, $mediaType)],
        'media_type'            => $mediaType,
    ]);
}

function getSeasonEpisodes(int $tvId, int $seasonNumber): array
{
    $data = fetchFromTMDB("/tv/{$tvId}/season/{$seasonNumber}", ['language' => 'en-US']);
    if ($data && !empty($data['episodes'])) {
        return $data['episodes'];
    }
    return get_placeholder_episodes();
}

function get_placeholder_episodes(int $count = 6): array
{
    $out = [];
    for ($i = 1; $i <= $count; $i++) {
        $out[] = [
            'id'             => $i,
            'episode_number' => $i,
            'name'           => 'Episode ' . $i,
            'overview'       => 'Episode details will appear here once the TMDB connection is live.',
            'still_path'     => null,
            'runtime'        => 42,
        ];
    }
    return $out;
}

function getCollectionParts(int $collectionId): array
{
    $data = fetchFromTMDB("/collection/{$collectionId}", ['language' => 'en-US']);
    if (!$data || empty($data['parts'])) {
        return [];
    }
    $parts = $data['parts'];
    usort($parts, function ($a, $b) {
        return strcmp($a['release_date'] ?? '9999', $b['release_date'] ?? '9999');
    });
    return $parts;
}

function getStillUrl(?string $path): ?string
{
    if (empty($path)) {
        return null;
    }
    return TMDB_IMAGE_BASE . $path;
}

/**
 * "Where to Watch" — TMDB's watch-providers endpoint (sourced from
 * JustWatch). Returns, per region, which legitimate services a title
 * streams/rents/buys on, plus a single `link` to the title's JustWatch
 * page for the full up-to-date list. TMDB's API terms require that link
 * (or equivalent JustWatch attribution) be shown alongside this data —
 * see https://developer.themoviedb.org/docs/watch-providers — so the
 * frontend must render both the providers and the link/attribution.
 *
 * No deep link to an individual provider's own player exists in this
 * data; that's by design on TMDB's side. `link` is the correct "go
 * watch it" destination — it does not embed or play anything itself.
 */
function getWatchProviders(int $id, string $mediaType, string $region = 'US'): array
{
    $mediaType = $mediaType === 'tv' ? 'tv' : 'movie';
    $region = strtoupper(preg_replace('/[^A-Za-z]/', '', $region) ?: 'US');

    $empty = ['region' => $region, 'link' => null, 'flatrate' => [], 'free' => [], 'ads' => [], 'rent' => [], 'buy' => []];

    if ($id <= 0) {
        return $empty;
    }

    $data = fetchFromTMDB("/{$mediaType}/{$id}/watch/providers", []);
    $results = $data['results'] ?? [];
    $forRegion = $results[$region] ?? $results['US'] ?? null;
    if (!$forRegion) {
        return $empty;
    }

    $mapProviders = function (array $providers): array {
        return array_map(function ($p) {
            return [
                'id'       => $p['provider_id'] ?? null,
                'name'     => $p['provider_name'] ?? '',
                'logo_url' => !empty($p['logo_path']) ? 'https://image.tmdb.org/t/p/w92' . $p['logo_path'] : '',
            ];
        }, $providers);
    };

    return [
        'region'   => $forRegion === ($results[$region] ?? null) ? $region : 'US',
        'link'     => $forRegion['link'] ?? null,
        'flatrate' => $mapProviders($forRegion['flatrate'] ?? []),
        'free'     => $mapProviders($forRegion['free'] ?? []),
        'ads'      => $mapProviders($forRegion['ads'] ?? []),
        'rent'     => $mapProviders($forRegion['rent'] ?? []),
        'buy'      => $mapProviders($forRegion['buy'] ?? []),
    ];
}
