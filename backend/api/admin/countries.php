<?php
/**
 * CineNova Admin API — countries.
 * ---------------------------------------------------------------
 * Same shape as admin/categories.php, backing Admin → Countries.
 * Source of truth: storage/countries.json.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../_helpers.php';

api_require_admin();

$method = api_resolve_method();
$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

if ($method === 'GET') {
    api_json(['success' => true, 'data' => ['countries' => store_read_json('countries.json', [])]]);
}

if ($method !== 'POST') {
    api_error('Method not allowed.', 405);
}
api_require_csrf();

$countries = store_read_json('countries.json', []);

if ($action === 'create') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    if ($name === '') {
        api_error('Country name is required.', 422);
    }
    foreach ($countries as $c) {
        if (strcasecmp($c['name'], $name) === 0) {
            api_error('That country already exists.', 422);
        }
    }
    $country = ['id' => store_next_id($countries), 'name' => $name, 'code' => $code];
    $countries[] = $country;
    store_write_json('countries.json', $countries);
    api_json(['success' => true, 'data' => ['country' => $country], 'message' => 'Country added.']);
}

if ($action === 'update') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    if ($name === '') {
        api_error('Country name is required.', 422);
    }

    $found = false;
    $oldName = null;
    foreach ($countries as &$c) {
        if ((int) $c['id'] === $id) {
            $oldName = $c['name'];
            $c['name'] = $name;
            $c['code'] = $code;
            $found = true;
            break;
        }
    }
    unset($c);
    if (!$found) {
        api_error('Country not found.', 404);
    }
    store_write_json('countries.json', $countries);

    if ($oldName !== null && $oldName !== $name) {
        $catalog = store_read_json('movies-catalog.json', []);
        foreach ($catalog as &$entry) {
            if (($entry['country'] ?? '') === $oldName) {
                $entry['country'] = $name;
            }
        }
        unset($entry);
        store_write_json('movies-catalog.json', $catalog);
    }

    api_json(['success' => true, 'message' => 'Country updated.']);
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $countries = array_values(array_filter($countries, fn($c) => (int) $c['id'] !== $id));
    store_write_json('countries.json', $countries);
    api_json(['success' => true, 'message' => 'Country removed.']);
}

api_error('Unknown action.', 400);
