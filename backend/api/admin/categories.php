<?php
/**
 * CineNova Admin API — categories.
 * ---------------------------------------------------------------
 * GET    /backend/api/admin/categories.php               -> list
 * POST   /backend/api/admin/categories.php  action=create {name}
 * POST   /backend/api/admin/categories.php  action=update {id, name}
 * POST   /backend/api/admin/categories.php  action=delete {id}
 *
 * Backs Admin → Categories. Data source is storage/categories.json —
 * the same file the public Agasobanuye/genre rows are grouped by
 * (via each catalog entry's own "genre" string), so renaming a
 * category here re-labels it everywhere it's used.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../_helpers.php';

api_require_admin();

$method = api_resolve_method();
$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

if ($method === 'GET') {
    api_json(['success' => true, 'data' => ['categories' => store_read_json('categories.json', [])]]);
}

if ($method !== 'POST') {
    api_error('Method not allowed.', 405);
}
api_require_csrf();

$categories = store_read_json('categories.json', []);

if ($action === 'create') {
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        api_error('Category name is required.', 422);
    }
    foreach ($categories as $c) {
        if (strcasecmp($c['name'], $name) === 0) {
            api_error('That category already exists.', 422);
        }
    }
    $category = ['id' => store_next_id($categories), 'name' => $name, 'slug' => store_slugify($name)];
    $categories[] = $category;
    store_write_json('categories.json', $categories);
    api_json(['success' => true, 'data' => ['category' => $category], 'message' => 'Category added.']);
}

if ($action === 'update') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        api_error('Category name is required.', 422);
    }

    $found = false;
    $oldName = null;
    foreach ($categories as &$c) {
        if ((int) $c['id'] === $id) {
            $oldName = $c['name'];
            $c['name'] = $name;
            $c['slug'] = store_slugify($name);
            $found = true;
            break;
        }
    }
    unset($c);
    if (!$found) {
        api_error('Category not found.', 404);
    }
    store_write_json('categories.json', $categories);

    // Keep every catalog entry's "genre" label in sync with the rename.
    if ($oldName !== null && $oldName !== $name) {
        $catalog = store_read_json('movies-catalog.json', []);
        foreach ($catalog as &$entry) {
            if (($entry['genre'] ?? '') === $oldName) {
                $entry['genre'] = $name;
            }
        }
        unset($entry);
        store_write_json('movies-catalog.json', $catalog);
    }

    api_json(['success' => true, 'message' => 'Category updated.']);
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $categories = array_values(array_filter($categories, fn($c) => (int) $c['id'] !== $id));
    store_write_json('categories.json', $categories);
    api_json(['success' => true, 'message' => 'Category removed.']);
}

api_error('Unknown action.', 400);
