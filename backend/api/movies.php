<?php
/**
 * API — Movies
 * GET    /backend/api/movies.php?q=fall                     search by title
 * GET    /backend/api/movies.php?id=1                       one movie + its sources
 * GET    /backend/api/movies.php?tmdb_id=985939&type=movie   lookup by TMDB id (used by watch.php)
 * POST   /backend/api/movies.php                             create (admin only)
 * POST   /backend/api/movies.php?_method=PUT&id=1             update (admin only)
 * POST   /backend/api/movies.php?_method=DELETE&id=1          delete (admin only)
 *
 * Creating/updating with a `tmdb_id` validates it against the real
 * TMDB API first (so a typo'd id can never be saved as if it were
 * real) and, when `use_vidsrc=1` is sent, automatically adds a
 * VidSrc source generated from that validated id — nothing manual
 * to type in for that source.
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

$method = api_resolve_method();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

switch ($method) {
    case 'GET':
        if ($id > 0) {
            $movie = Movie::find($id);
            if (!$movie) api_error('Movie not found.', 404);
            $movie['sources'] = Movie::sources($id);
            api_json(['data' => $movie]);
        }

        $tmdbId = filter_input(INPUT_GET, 'tmdb_id', FILTER_VALIDATE_INT) ?: 0;
        if ($tmdbId > 0) {
            $type = ($_GET['type'] ?? 'movie') === 'tv' ? 'tv' : 'movie';
            $movie = Movie::findByTmdbId($tmdbId, $type);
            if (!$movie) api_error('Movie not found.', 404);
            $movie['sources'] = Movie::sources((int) $movie['id']);
            api_json(['data' => $movie]);
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $type = $_GET['type'] ?? null;
        if ($query !== '') {
            api_json(['data' => Movie::searchByTitle($query, $type, 20)]);
        }
        api_json(['data' => Movie::all()]);
        break;

    case 'POST':
        api_require_admin();
        api_require_csrf();
        $result = api_save_movie(null);
        api_json(['data' => $result, 'message' => 'Movie created.'], 201);
        break;

    case 'PUT':
        api_require_admin();
        api_require_csrf();
        if ($id <= 0 || !Movie::find($id)) api_error('Movie not found.', 404);
        $result = api_save_movie($id);
        api_json(['data' => $result, 'message' => 'Movie updated.']);
        break;

    case 'DELETE':
        api_require_admin();
        api_require_csrf();
        if ($id <= 0 || !Movie::find($id)) api_error('Movie not found.', 404);
        Movie::delete($id); // movie_sources rows cascade automatically
        api_json(['message' => 'Movie deleted.']);
        break;

    default:
        api_error('Method not allowed.', 405);
}

/**
 * Shared create/update path. Validates every field, verifies a
 * given tmdb_id against the real TMDB API, and — when requested —
 * generates a VidSrc source from that verified id.
 */
function api_save_movie(?int $existingId): array
{
    $title = trim((string) ($_POST['title'] ?? ''));
    $tmdbIdRaw = trim((string) ($_POST['tmdb_id'] ?? ''));
    $mediaType = ($_POST['media_type'] ?? 'movie') === 'tv' ? 'tv' : 'movie';
    $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: null;
    $countryId = filter_input(INPUT_POST, 'country_id', FILTER_VALIDATE_INT) ?: null;
    $year = trim((string) ($_POST['year'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $posterUrl = trim((string) ($_POST['poster_url'] ?? ''));
    $useVidsrc = !empty($_POST['use_vidsrc']);

    $errors = [];
    if ($title === '') $errors[] = 'Title is required.';
    if ($year !== '' && !preg_match('/^\d{4}$/', $year)) $errors[] = 'Year must be a 4-digit year.';
    if ($categoryId !== null && !Category::find($categoryId)) $errors[] = 'Selected category does not exist.';
    if ($countryId !== null && !Country::find($countryId)) $errors[] = 'Selected country does not exist.';

    $tmdbId = 0;
    $tmdbDetails = null;
    if ($tmdbIdRaw !== '') {
        $tmdbId = (int) $tmdbIdRaw;
        if ($tmdbId <= 0) {
            $errors[] = 'TMDB ID must be a positive number.';
        } else {
            // Validate against the real TMDB API — a fabricated or
            // mistyped id is rejected here rather than silently saved.
            $tmdbDetails = fetchFromTMDB("/{$mediaType}/{$tmdbId}", ['language' => 'en-US']);
            if (empty($tmdbDetails['id'])) {
                $errors[] = "TMDB ID {$tmdbId} could not be verified against TMDB for a {$mediaType}. Leave it blank for original/uploaded content not listed on TMDB.";
            }
        }
    }

    $sourcesRaw = (string) ($_POST['sources'] ?? ''); // "Label | URL" lines, one per source
    $sources = api_parse_source_lines($sourcesRaw);
    if (empty($sources) && !$useVidsrc) {
        $errors[] = 'At least one playable source is required (an embed/upload line, or enable VidSrc).';
    }

    if (!empty($errors)) {
        api_error(implode(' ', $errors), 422);
    }

    $slug = api_slugify($title);
    if (Movie::slugTaken($slug, $existingId ?? 0)) {
        $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 5);
    }

    $source = $tmdbId > 0 ? 'tmdb' : 'local';
    if ($source === 'local') {
        // Same synthetic-id scheme the admin panel already uses, so a
        // custom title still gets a normal, stable watch.php URL.
        $tmdbId = 900000000 + (crc32($slug) % 99999999);
    } elseif ($tmdbDetails) {
        // Verified real title — TMDB's own poster/year/title win unless the admin explicitly overrode them.
        if ($posterUrl === '' && !empty($tmdbDetails['poster_path'])) {
            $posterUrl = 'https://image.tmdb.org/t/p/w342' . $tmdbDetails['poster_path'];
        }
        if ($year === '') {
            $date = $tmdbDetails['release_date'] ?? $tmdbDetails['first_air_date'] ?? '';
            if ($date) $year = date('Y', strtotime($date));
        }
    }

    if ($useVidsrc && $tmdbId > 0 && $source === 'tmdb') {
        $sources[] = ['label' => 'VidSrc', 'url' => vidsrc_embed_url($tmdbId, $mediaType), 'source_type' => 'vidsrc'];
    }

    $payload = [
        'slug' => $slug, 'title' => $title, 'tmdb_id' => $tmdbId, 'source' => $source,
        'media_type' => $mediaType, 'category_id' => $categoryId, 'country_id' => $countryId,
        'year' => $year ?: null, 'description' => $description ?: null, 'poster_url' => $posterUrl ?: null,
        'rating' => (float) ($tmdbDetails['vote_average'] ?? 0),
    ];

    $movieId = $existingId;
    if ($movieId) {
        Movie::update($movieId, $payload);
    } else {
        $movieId = Movie::create($payload);
    }
    Movie::replaceSources($movieId, $sources);

    $movie = Movie::find($movieId);
    $movie['sources'] = Movie::sources($movieId);
    return $movie;
}

/** Same "Label | URL" textarea format the admin panel already uses. */
function api_parse_source_lines(string $raw): array
{
    $sources = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, '|')) continue;
        [$label, $url] = array_map('trim', explode('|', $line, 2));
        if ($label === '' || $url === '') continue;
        $isValidEmbed = filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url);
        $isLocalUpload = str_contains($url, 'uploads/videos/') && !str_contains($url, '..');
        if (!$isValidEmbed && !$isLocalUpload) continue;
        $sources[] = ['label' => $label, 'url' => $url, 'source_type' => str_contains($url, 'uploads/videos/') ? 'upload' : 'embed'];
    }
    return $sources;
}
