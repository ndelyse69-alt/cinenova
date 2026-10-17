<?php
/**
 * CineNova Admin API — movie/series catalog.
 * ---------------------------------------------------------------
 * GET    /backend/api/admin/movies.php                     -> list all catalog entries
 * GET    /backend/api/admin/movies.php?slug=the-croods      -> one entry
 * POST   /backend/api/admin/movies.php  action=create        {...fields, poster upload optional}
 * POST   /backend/api/admin/movies.php  action=update        {slug, ...fields}
 * POST   /backend/api/admin/movies.php  action=delete        {slug}
 * POST   /backend/api/admin/movies.php  action=toggle_vidsrc {slug}
 *
 * Backs Admin → Movies. This is the single JSON file
 * (storage/movies-catalog.json) the public site's watch page and
 * Agasobanuye rows read from — an entry saved here is live
 * immediately, no separate publish step.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../_helpers.php';

api_require_admin();

$method = api_resolve_method();
$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

$catalog = store_read_json('movies-catalog.json', []);

if ($method === 'GET') {
    $slug = (string) ($_GET['slug'] ?? '');
    if ($slug !== '') {
        if (!isset($catalog[$slug])) {
            api_error('Title not found.', 404);
        }
        $entry = $catalog[$slug];
        $entry['slug'] = $slug;
        $entry['episodes_text'] = store_episodes_to_lines($entry['episodes'] ?? []);
        api_json(['success' => true, 'data' => ['movie' => $entry]]);
    }

    $list = [];
    foreach ($catalog as $slug => $entry) {
        $list[] = [
            'slug' => $slug,
            'title' => $entry['title'] ?? 'Untitled',
            'tmdb_id' => $entry['tmdb_id'] ?? null,
            'media_type' => $entry['media_type'] ?? 'movie',
            'genre' => $entry['genre'] ?? '',
            'country' => $entry['country'] ?? '',
            'year' => $entry['year'] ?? '',
            'source' => $entry['source'] ?? 'tmdb',
            'poster_url' => $entry['poster_url'] ?? null,
            'episode_count' => count($entry['episodes'] ?? []),
            'use_vidsrc' => !empty($entry['use_vidsrc']),
        ];
    }
    api_json(['success' => true, 'data' => ['movies' => $list]]);
}

if ($method !== 'POST') {
    api_error('Method not allowed.', 405);
}
api_require_csrf();

function admin_movies_read_form(): array
{
    $errors = [];
    $title = trim((string) ($_POST['title'] ?? ''));
    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    $mediaType = ($_POST['media_type'] ?? 'movie') === 'tv' ? 'tv' : 'movie';
    $source = ($_POST['source'] ?? 'tmdb') === 'local' ? 'local' : 'tmdb';
    $tmdbId = (int) ($_POST['tmdb_id'] ?? 0);

    $useVidsrc = !empty($_POST['use_vidsrc']);
    $episodesText = (string) ($_POST['episodes_text'] ?? '');
    $episodes = store_parse_episode_lines($episodesText);

    if (!$useVidsrc && empty($episodes)) {
        $errors[] = 'At least one playable source is required (an embed/upload line, or enable VidSrc).';
    }
    if ($source === 'tmdb' && $tmdbId <= 0 && !$useVidsrc) {
        // VidSrc needs a real TMDB id to build its embed URL from.
        $errors[] = 'A TMDB id is required to use VidSrc for a non-local title.';
    }

    $posterUploadResult = null;
    if (!empty($_FILES['poster_file']) && ($_FILES['poster_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $posterUploadResult = upload_handle_poster($_FILES['poster_file']);
        if (!$posterUploadResult['ok']) {
            $errors[] = $posterUploadResult['error'];
        }
    }

    $videoUploadResult = null;
    if (!empty($_FILES['video_file']) && ($_FILES['video_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $videoUploadResult = upload_handle_video($_FILES['video_file']);
        if (!$videoUploadResult['ok']) {
            $errors[] = $videoUploadResult['error'];
        } else {
            $label = trim((string) ($_POST['video_label'] ?? 'Full Movie'));
            $episodes[$label] = $videoUploadResult['url'];
        }
    }

    return [
        'errors' => $errors,
        'entry' => [
            'title' => $title,
            'tmdb_id' => $source === 'local' && $tmdbId <= 0 ? null : $tmdbId,
            'source' => $source,
            'media_type' => $mediaType,
            'genre' => trim((string) ($_POST['genre'] ?? '')),
            'country' => trim((string) ($_POST['country'] ?? '')),
            'year' => trim((string) ($_POST['year'] ?? '')),
            'rating' => (float) ($_POST['rating'] ?? 0),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'poster_url' => upload_resolve_poster($_POST['poster_url'] ?? null, $posterUploadResult['url'] ?? null),
            'use_vidsrc' => $useVidsrc,
            'episodes' => $episodes,
        ],
    ];
}

if ($action === 'create') {
    $form = admin_movies_read_form();
    if (!empty($form['errors'])) {
        api_error(implode(' ', $form['errors']), 422, ['errors' => $form['errors']]);
    }

    $entry = $form['entry'];
    if (empty($entry['tmdb_id']) && $entry['source'] === 'local') {
        $entry['tmdb_id'] = store_synthetic_id(store_slugify($entry['title']));
    }

    $slug = store_slugify($entry['title']);
    if (isset($catalog[$slug])) {
        $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
    }
    $catalog[$slug] = $entry;
    store_write_json('movies-catalog.json', $catalog);

    // Publish a user-facing update automatically whenever a new title is added.
    try {
        UpdateService::add('New ' . ($entry['media_type'] === 'tv' ? 'series' : 'movie') . ' added: ' . $entry['title'], $slug, $entry['title']);
    } catch (Throwable $e) {
        // A notification failure must never prevent the movie from being published.
    }

    api_json(['success' => true, 'data' => ['slug' => $slug], 'message' => 'Title added to the catalog.']);
}

if ($action === 'update') {
    $slug = (string) ($_POST['slug'] ?? '');
    if (!isset($catalog[$slug])) {
        api_error('Title not found.', 404);
    }

    $form = admin_movies_read_form();
    if (!empty($form['errors'])) {
        api_error(implode(' ', $form['errors']), 422, ['errors' => $form['errors']]);
    }

    $entry = $form['entry'];
    if (empty($entry['tmdb_id'])) {
        $entry['tmdb_id'] = $catalog[$slug]['tmdb_id'] ?? store_synthetic_id($slug);
    }
    // A blank poster field on update means "keep the existing poster".
    if (empty($entry['poster_url'])) {
        $entry['poster_url'] = $catalog[$slug]['poster_url'] ?? null;
    }

    $catalog[$slug] = $entry;
    store_write_json('movies-catalog.json', $catalog);

    api_json(['success' => true, 'message' => 'Title updated.']);
}

if ($action === 'delete') {
    $slug = (string) ($_POST['slug'] ?? '');
    unset($catalog[$slug]);
    store_write_json('movies-catalog.json', $catalog);
    api_json(['success' => true, 'message' => 'Title removed from the catalog.']);
}

if ($action === 'toggle_vidsrc') {
    $slug = (string) ($_POST['slug'] ?? '');
    if (!isset($catalog[$slug])) {
        api_error('Title not found.', 404);
    }
    $catalog[$slug]['use_vidsrc'] = empty($catalog[$slug]['use_vidsrc']);
    store_write_json('movies-catalog.json', $catalog);
    api_json(['success' => true, 'data' => ['use_vidsrc' => $catalog[$slug]['use_vidsrc']], 'message' => 'Updated.']);
}

api_error('Unknown action.', 400);
