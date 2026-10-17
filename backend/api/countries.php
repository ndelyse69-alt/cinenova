<?php
/**
 * API — Countries
 * GET    /backend/api/countries.php
 * GET    /backend/api/countries.php?id=1
 * POST   /backend/api/countries.php                        create (admin only)
 * POST   /backend/api/countries.php?_method=PUT&id=1        update (admin only)
 * POST   /backend/api/countries.php?_method=DELETE&id=1     delete (admin only)
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

$method = api_resolve_method();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

function api_validate_country(string $name, string $code): array
{
    $errors = [];
    if ($name === '') $errors[] = 'Country name is required.';
    if (!preg_match('/^[A-Z]{2}$/', $code)) $errors[] = 'Country code must be exactly 2 letters (e.g. US, RW).';
    return $errors;
}

switch ($method) {
    case 'GET':
        if ($id > 0) {
            $country = Country::find($id);
            if (!$country) api_error('Country not found.', 404);
            api_json(['data' => $country]);
        }
        api_json(['data' => Country::all()]);
        break;

    case 'POST':
        api_require_admin();
        api_require_csrf();

        $name = trim((string) ($_POST['name'] ?? ''));
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        $errors = api_validate_country($name, $code);
        if (!empty($errors)) api_error(implode(' ', $errors), 422);

        $newId = Country::create($name, $code, $status);
        api_json(['data' => Country::find($newId), 'message' => 'Country created.'], 201);
        break;

    case 'PUT':
        api_require_admin();
        api_require_csrf();

        if ($id <= 0 || !Country::find($id)) api_error('Country not found.', 404);

        $name = trim((string) ($_POST['name'] ?? ''));
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        $errors = api_validate_country($name, $code);
        if (!empty($errors)) api_error(implode(' ', $errors), 422);

        Country::update($id, $name, $code, $status);
        api_json(['data' => Country::find($id), 'message' => 'Country updated.']);
        break;

    case 'DELETE':
        api_require_admin();
        api_require_csrf();

        if ($id <= 0 || !Country::find($id)) api_error('Country not found.', 404);
        Country::delete($id);
        api_json(['message' => 'Country deleted.']);
        break;

    default:
        api_error('Method not allowed.', 405);
}
