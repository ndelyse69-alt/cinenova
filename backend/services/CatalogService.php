<?php
/**
 * CineNova — Embed/Catalog service.
 * ---------------------------------------------------------------
 * SINGLE SOURCE OF TRUTH for every playable embed token/URL on the
 * site is backend/storage/movies-catalog.json — managed live from
 * the Admin Panel (Admin → Movies / Upload Video). Every public
 * lookup (watch page, Agasobanuye page, search-merge) goes through
 * this file, keyed by (tmdb_id, media_type) — the same "reliable
 * unique movie ID" used everywhere on the site.
 *
 * Schema per entry (keyed by a slug):
 *   'title'      => display title (fallback only — the real title
 *                   comes from the live TMDB record when available)
 *   'tmdb_id'    => verified TMDB id (int) or a synthetic local id
 *   'source'     => 'tmdb' | 'local'
 *   'media_type' => 'movie' | 'tv'
 *   'genre'      => curated row this title appears under
 *   'episodes'   => ordered ['Label' => 'https://embed-url', ...]
 */
declare(strict_types=1);

define('CATALOG_JSON_PATH', __DIR__ . '/../storage/movies-catalog.json');

function loadEmbedCatalog(): array
{
    static $raw = null;
    if ($raw === null) {
        $json = @file_get_contents(CATALOG_JSON_PATH);
        $data = $json !== false ? json_decode($json, true) : null;
        $raw = is_array($data) ? $data : [];
    }
    return $raw;
}

/**
 * Look up a curated embed entry by (tmdb_id, media_type) — the same
 * pair used everywhere else on the site.
 */
function findEmbedCatalogEntry(int $tmdbId, string $mediaType): ?array
{
    static $index = null;
    if ($index === null) {
        $index = [];
        foreach (loadEmbedCatalog() as $slug => $entry) {
            $key = ($entry['media_type'] ?? 'movie') . ':' . (int) ($entry['tmdb_id'] ?? 0);
            $index[$key] = array_merge($entry, ['slug' => $slug]);
        }
    }
    $key = ($mediaType === 'tv' ? 'tv' : 'movie') . ':' . $tmdbId;
    return $index[$key] ?? null;
}

/** Curated catalog grouped by genre, for the Agasobanuye page rows. */
function getAgasobanuyeData(): array
{
    $grouped = [];
    foreach (loadEmbedCatalog() as $slug => $entry) {
        $genre = $entry['genre'] ?? 'Other';
        $grouped[$genre][] = array_merge($entry, ['slug' => $slug]);
    }
    return $grouped;
}

/**
 * Fetch live TMDB details for a curated entry using its verified
 * tmdb_id + media_type (never a title search).
 */
function enrichCuratedMovie(array $movie): array
{
    $tmdbId    = (int) ($movie['tmdb_id'] ?? 0);
    $mediaType = ($movie['media_type'] ?? 'movie') === 'tv' ? 'tv' : 'movie';
    $title     = $movie['title'] ?? 'Untitled';
    $isLocal   = ($movie['source'] ?? 'tmdb') === 'local';

    if ($isLocal) {
        $details = build_local_title_details($movie, $tmdbId, $mediaType);
    } else {
        $details = $tmdbId > 0
            ? fetchFromTMDB("/{$mediaType}/{$tmdbId}", ['language' => 'en-US', 'append_to_response' => 'videos'])
            : null;

        if (empty($details['id'])) {
            $details = [
                'id'                => $tmdbId,
                'title'             => $title,
                'name'              => $title,
                'overview'          => 'Synopsis will be available soon.',
                'poster_path'       => null,
                'backdrop_path'     => null,
                'vote_average'      => 0,
                'release_date'      => '',
                'first_air_date'    => '',
                'original_language' => 'en',
                'videos'            => ['results' => []],
            ];
        }
    }

    $trailerKey = $isLocal ? null : getTrailerKey($details);

    $year = '';
    if (!empty($details['release_date'])) {
        $year = date('Y', strtotime($details['release_date']));
    } elseif (!empty($details['first_air_date'])) {
        $year = date('Y', strtotime($details['first_air_date']));
    }
    if ($year === '' && !empty($movie['year'])) {
        $year = (string) $movie['year'];
    }

    $posterPath  = !empty($movie['poster_url']) ? $movie['poster_url'] : ($details['poster_path'] ?? null);
    $description = !empty($movie['description']) ? $movie['description'] : ($details['overview'] ?? 'No description available.');
    $rating      = !empty($movie['rating']) ? (float) $movie['rating'] : (float) ($details['vote_average'] ?? 0);

    return [
        'id'            => $tmdbId,
        'tmdb_id'       => $tmdbId,
        'media_type'    => $mediaType,
        'title'         => $isLocal ? $title : (titleOf($details) ?: $title),
        'overview'      => $description,
        'poster_path'   => $posterPath,
        'backdrop_path' => $details['backdrop_path'] ?? null,
        'vote_average'  => $rating,
        'year'          => $year,
        'country'       => $movie['country'] ?? '',
        'trailer_key'   => $trailerKey,
        'episodes'      => $movie['episodes'] ?? [],
        'slug'          => $movie['slug'] ?? null,
        'is_tv'         => $mediaType === 'tv',
    ];
}

/**
 * Build a TMDB-details-shaped array purely from a catalog entry's
 * own stored fields — used for entries with no real TMDB match.
 */
function build_local_title_details(array $entry, int $id, string $mediaType): array
{
    $title = $entry['title'] ?? 'Untitled';
    $year  = trim((string) ($entry['year'] ?? ''));
    $genreName = trim((string) ($entry['genre'] ?? ''));

    return [
        'id'                    => $id,
        'title'                 => $title,
        'name'                  => $title,
        'media_type'            => $mediaType,
        'overview'              => $entry['description'] ?? 'No description available.',
        'poster_path'           => $entry['poster_url'] ?? null,
        'backdrop_path'         => null,
        'vote_average'          => (float) ($entry['rating'] ?? 0),
        'release_date'          => $mediaType === 'movie' && $year !== '' ? $year . '-01-01' : '',
        'first_air_date'        => $mediaType === 'tv' && $year !== '' ? $year . '-01-01' : '',
        'original_language'     => 'en',
        'genres'                => $genreName !== '' ? [['id' => 0, 'name' => $genreName]] : [],
        'production_countries'  => !empty($entry['country']) ? [['name' => $entry['country']]] : [],
        'videos'                => ['results' => []],
        'credits'               => ['cast' => []],
        'similar'               => ['results' => []],
    ];
}

/** Full curated list with TMDB enrichment, grouped by genre. */
function getAgasobanuyeMovies(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $raw = getAgasobanuyeData();
    $enriched = [];
    foreach ($raw as $genre => $movies) {
        $enriched[$genre] = array_map('enrichCuratedMovie', $movies);
    }
    $cache = $enriched;
    return $enriched;
}

/**
 * Convert a movies-catalog.json entry into the TMDB-item shape the
 * rest of the app expects, so a locally-catalogued title (uploaded
 * or curated) renders as an identical card to a real TMDB result.
 */
function catalogEntryToCardItem(array $entry): array
{
    $year = trim((string) ($entry['year'] ?? ''));
    $mediaType = ($entry['media_type'] ?? 'movie') === 'tv' ? 'tv' : 'movie';

    return [
        'id'             => (int) ($entry['tmdb_id'] ?? 0),
        'title'          => $entry['title'] ?? 'Untitled',
        'name'           => $entry['title'] ?? 'Untitled',
        'poster_path'    => $entry['poster_url'] ?? null,
        'vote_average'   => (float) ($entry['rating'] ?? 0),
        'release_date'   => $mediaType === 'movie' && $year !== '' ? $year . '-01-01' : '',
        'first_air_date' => $mediaType === 'tv' && $year !== '' ? $year . '-01-01' : '',
        'genre_name'     => $entry['genre'] ?? '',
    ];
}
