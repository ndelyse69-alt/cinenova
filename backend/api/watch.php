<?php
/**
 * CineNova API — watch page data.
 * ---------------------------------------------------------------
 * GET /backend/api/watch.php?id=123&type=movie
 * GET /backend/api/watch.php?id=123&type=tv&season=1&episode=2
 *
 * Ported from the original watch.php's data-assembly logic exactly:
 * same right-rail priority order (curated embed items > TV season
 * episodes > movie collection parts > single "Feature Presentation"
 * card), same VidSrc fallback rule, same quick-facts grid, same
 * comments key. The only thing that changed is *where* this runs —
 * server-side PHP building HTML before, JSON for client-side
 * rendering now.
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

if (api_resolve_method() !== 'GET') {
    api_error('Method not allowed.', 405);
}

$id = (int) ($_GET['id'] ?? 0);
$mediaType = ($_GET['type'] ?? 'movie') === 'tv' ? 'tv' : 'movie';

$catalogEntry = findEmbedCatalogEntry($id, $mediaType);
$isLocal = $catalogEntry && ($catalogEntry['source'] ?? 'tmdb') === 'local';

$details = getTitleDetails($id, $mediaType);
if ($isLocal && $catalogEntry) {
    // Keep the curated/local title metadata when TMDB cannot resolve it,
    // but still use the real TMDB record whenever the curated entry has
    // a valid TMDB id so cast/recommendations can work for it too.
    $localDetails = build_local_title_details($catalogEntry, $id, $mediaType);
    if (empty($details['id']) || str_contains((string) ($details['overview'] ?? ''), 'Details for this title will appear here')) {
        $details = $localDetails;
    }
}

$title = titleOf($details);
analytics_track_visit('watch:' . $mediaType . ':' . $id . ':' . $title);

// -------------------------------------------------------------
// Playback sources — every title with a TMDB id gets VidSrc.
// Local/curated entries are not excluded: if their numeric id maps
// to TMDB, use the same playback rules as every other title.
// -------------------------------------------------------------
$embedItems = [];
$season = isset($_GET['season']) ? max(1, (int) $_GET['season']) : 1;
$episodeNum = isset($_GET['episode']) ? max(1, (int) $_GET['episode']) : 1;

// Curated Agasobanuye titles can have their own full-movie/episode embeds.
// Use those first so clicking a curated title on Agasobanuye opens watch.html
// and immediately has the intended playable source available.
$curatedEpisodes = $catalogEntry['episodes'] ?? [];
if ($mediaType === 'movie' && is_array($curatedEpisodes)) {
    foreach ($curatedEpisodes as $label => $url) {
        $url = trim((string) $url);
        if ($url === '') continue;
        $sourceType = preg_match('#(?:^|/)uploads/videos/#i', $url) || preg_match('#\.(mp4|webm|m4v|mov)(?:[?#].*)?$#i', $url) ? 'direct' : 'embed';
        $embedItems[] = [
            'label' => (string) ($label ?: 'Full Movie'),
            'url' => $url,
            'source_type' => $sourceType,
        ];
    }
}

// For normal New Releases/TMDB titles, or curated titles without an explicit
// source, fall back to the generated VidSrc player URLs.
if ($id > 0 && $mediaType === 'movie' && empty($embedItems)) {
    foreach (vidsrc_embed_urls($id, 'movie') as $index => $url) {
        $embedItems[] = [
            'label' => $index === 0 ? 'Full Movie' : 'Full Movie — Backup Player',
            'url' => $url,
            'source_type' => 'vidsrc',
        ];
    }
}

// -------------------------------------------------------------
// Right rail: TV = TMDB season episodes, each mapped to VidSrc.
// Movies = one full movie plus a backup player URL.
// -------------------------------------------------------------
$episodeList = [];
$collectionParts = [];
$seasonsList = [];
$switcherHeading = 'This Title';

if ($mediaType === 'movie' && !empty($embedItems)) {
    $switcherHeading = 'This Title';
    foreach ($embedItems as $item) {
        $episodeList[] = [
            'kind' => 'embed',
            'label' => $item['label'],
            'url' => $item['url'],
            'fallback_url' => $embedItems[1]['url'] ?? null,
            'source_type' => $item['source_type'],
            'download_url' => in_array($item['source_type'], ['upload', 'direct'], true) ? $item['url'] : null,
        ];
    }
} elseif ($mediaType === 'tv') {
    $seasonsList = array_values(array_filter($details['seasons'] ?? [], fn($s) => ($s['season_number'] ?? 0) > 0));
    $seasonNumber = $season;
    if (!$seasonNumber) {
        $seasonNumber = $seasonsList[0]['season_number'] ?? 1;
    }
    $rawEpisodes = getSeasonEpisodes($id, $seasonNumber);
    $switcherHeading = 'Episodes — Season ' . $seasonNumber;
    foreach ($rawEpisodes as $i => $ep) {
        $epNum = (int) ($ep['episode_number'] ?? ($i + 1));
        $episodeUrls = vidsrc_embed_urls($id, 'tv', $seasonNumber, $epNum);
        $episodeList[] = [
            'kind' => 'episode',
            'episode_number' => $epNum,
            'name' => $ep['name'] ?? ('Episode ' . $epNum),
            'overview' => $ep['overview'] ?? 'No summary available.',
            'still_url' => getStillUrl($ep['still_path'] ?? null),
            'watch_url' => $episodeUrls[0] ?? null,
            'fallback_watch_url' => $episodeUrls[1] ?? null,
        ];
    }
} elseif ($mediaType === 'movie' && !empty($details['belongs_to_collection']['id'])) {
    $collectionParts = getCollectionParts((int) $details['belongs_to_collection']['id']);
    $switcherHeading = $details['belongs_to_collection']['name'] ?? 'Collection';
    foreach ($collectionParts as $part) {
        $episodeList[] = [
            'kind' => 'part',
            'id' => $part['id'],
            'title' => titleOf($part),
            'poster_url' => getPosterUrl($part['poster_path'] ?? null, 'w92'),
            'watch_url' => watchUrl($part, 'movie'),
            'year' => !empty($part['release_date']) ? substr($part['release_date'], 0, 4) : '',
            'is_current' => (int) $part['id'] === $id,
        ];
    }
}

// -------------------------------------------------------------
// Quick-facts grid (Section 2) — same fields/order as the original.
// -------------------------------------------------------------
$facts = [];
$facts[] = ['Status', $details['status'] ?? ''];
$facts[] = ['Original Language', strtoupper($details['original_language'] ?? '')];
$facts[] = ['Release Date', $details['release_date'] ?? $details['first_air_date'] ?? ''];
if ($mediaType === 'movie') {
    $budget = (int) ($details['budget'] ?? 0);
    $revenue = (int) ($details['revenue'] ?? 0);
    $facts[] = ['Budget', $budget > 0 ? '$' . number_format($budget) : 'Not disclosed'];
    $facts[] = ['Revenue', $revenue > 0 ? '$' . number_format($revenue) : 'Not disclosed'];
} else {
    $facts[] = ['Seasons', (string) ($details['number_of_seasons'] ?? '')];
    $facts[] = ['Episodes', (string) ($details['number_of_episodes'] ?? '')];
}
$facts = array_values(array_filter($facts, fn($f) => $f[1] !== ''));

$cast = array_map(function ($member) {
    return [
        'name' => $member['name'] ?? '',
        'character' => $member['character'] ?? '',
        'profile_url' => getProfileUrl($member['profile_path'] ?? null),
    ];
}, $isLocal ? [] : getCast($details, 30));

// Cheap genre-name lookup (file-cached, one request per media type,
// reused across every recommended card below — no per-item API calls).
$genreMap = [];
foreach (getGenres($mediaType) as $g) {
    $genreMap[(int) $g['id']] = $g['name'];
}

$similar = array_map(function ($item) use ($mediaType, $genreMap) {
    $genreIds = $item['genre_ids'] ?? [];
    $genreNames = array_values(array_filter(array_map(fn($gid) => $genreMap[(int) $gid] ?? null, array_slice($genreIds, 0, 3))));
    return [
        'id' => $item['id'],
        'title' => titleOf($item),
        'poster_url' => getPosterUrl($item['poster_path'] ?? null),
        'vote_average' => $item['vote_average'] ?? 0,
        'year' => yearOf($item),
        'watch_url' => watchUrl($item, $item['media_type'] ?? $mediaType),
        'genres' => $genreNames,
        'media_type' => $item['media_type'] ?? $mediaType,
    ];
}, $isLocal ? [] : getRecommendedTitles($details, $mediaType, 12));

$country = $details['production_countries'][0]['name'] ?? ($catalogEntry['country'] ?? '');
$runtimeMin = $mediaType === 'movie' ? (int) ($details['runtime'] ?? 0) : (int) ($details['episode_run_time'][0] ?? 0);
$backdrop = getBackdropUrl($details['backdrop_path'] ?? null, 'original');
$playerImage = $backdrop ?: getPosterUrl($details['poster_path'] ?? null, 'w780');

api_json(['success' => true, 'data' => [
    'id'                => $id,
    'media_type'        => $mediaType,
    'title'             => $title,
    'tagline'           => trim((string) ($details['tagline'] ?? '')),
    'overview'          => $details['overview'] ?? 'No description available yet.',
    'poster_url'        => getPosterUrl($details['poster_path'] ?? null),
    'backdrop_url'      => $backdrop,
    'player_image'      => $playerImage,
    'vote_average'      => number_format((float) ($details['vote_average'] ?? 0), 1),
    'year'              => yearOf($details),
    'runtime'           => formatRuntime($runtimeMin),
    'genres'            => array_map(fn($g) => $g['name'], array_slice($details['genres'] ?? [], 0, 4)),
    'country'           => $country,
    'trailer_key'       => $isLocal ? null : getTrailerKey($details),
    'cast'              => $cast,
    'facts'             => $facts,
    'similar'           => $similar,
    'vidsrc_base_url'  => rtrim(VIDSRC_BASE_URL, '/'),
    'vidsrc_urls'      => $mediaType === 'movie' ? vidsrc_embed_urls($id, 'movie') : vidsrc_embed_urls($id, 'tv', $season, $episodeNum),
    'vidsrc_url'       => $mediaType === 'movie' ? (vidsrc_embed_urls($id, 'movie')[0] ?? null) : (vidsrc_embed_urls($id, 'tv', $season, $episodeNum)[0] ?? null),
    'switcher_heading'  => $switcherHeading,
    'episode_items'     => $episodeList,
    'season'            => $season,
    'episode'           => $episodeNum,
    'watch_full_label'  => $mediaType === 'movie' ? 'Watch Full Movie' : 'Watch Full Episode',
    'browse_href'       => $mediaType === 'tv' ? 'series.html' : 'movies.html',
    'browse_label'      => $mediaType === 'tv' ? 'Series' : 'Movies',
    'comments_key'      => 'cinenova_comments_' . $mediaType . '_' . $id,
    'is_curated'        => $catalogEntry !== null,
]]);
