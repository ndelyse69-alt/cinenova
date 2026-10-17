<?php
/**
 * CineNova Admin API — site settings.
 * ---------------------------------------------------------------
 * GET  /backend/api/admin/settings.php          -> current settings
 * POST /backend/api/admin/settings.php           -> update (any subset of fields)
 *
 * Backs Admin → Settings. Stored in storage/settings.json as a
 * single object (not a list), read by the public site for things
 * like the site name/tagline/maintenance flag.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../_helpers.php';

$method = api_resolve_method();

$defaults = [
    'site_name' => SITE_NAME,
    'tagline' => SITE_TAGLINE,
    'description' => SITE_DESCRIPTION,
    'maintenance_mode' => false,
    'contact_email' => 'support@cinenova.example',
    'tmdb_enabled' => true,
];

if ($method === 'GET') {
    $settings = array_merge($defaults, store_read_json('settings.json', []));
    api_json(['success' => true, 'data' => ['settings' => $settings]]);
}

if ($method !== 'POST') {
    api_error('Method not allowed.', 405);
}

api_require_admin();
api_require_csrf();

$settings = array_merge($defaults, store_read_json('settings.json', []));

foreach (['site_name', 'tagline', 'description', 'contact_email'] as $field) {
    if (isset($_POST[$field])) {
        $settings[$field] = trim((string) $_POST[$field]);
    }
}
foreach (['maintenance_mode', 'tmdb_enabled'] as $field) {
    if (isset($_POST[$field])) {
        $settings[$field] = in_array($_POST[$field], ['1', 'true', 'on', true], true);
    }
}

store_write_json('settings.json', $settings);
api_json(['success' => true, 'data' => ['settings' => $settings], 'message' => 'Settings saved.']);
