<?php
/**
 * API — Categories
 * GET    /backend/api/categories.php            list all
 * GET    /backend/api/categories.php?id=1        one category
 * POST   /backend/api/categories.php             create   (admin only)
 * POST   /backend/api/categories.php?_method=PUT&id=1     update (admin only)
 * POST   /backend/api/categories.php?_method=DELETE&id=1  delete (admin only)
 *
 * (POST + _method override is used instead of real PUT/DELETE verbs
 * so this works from a plain HTML form as well as fetch(); every
 * mutating action still requires an authenticated admin session +
 * a valid CSRF token, exactly like the rest of the admin panel.)
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

$method = api_resolve_method();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

switch ($method) {
    case 'GET':
        if ($id > 0) {
            $category = Category::find($id);
            if (!$category) {
                api_error('Category not found.', 404);
            }
            api_json(['data' => $category]);
        }
        api_json(['data' => Category::all()]);
        break;

    case 'POST':
        api_require_admin();
        api_require_csrf();

        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        $errors = [];
        if ($name === '') $errors[] = 'Name is required.';
        if (strlen($name) > 100) $errors[] = 'Name must be under 100 characters.';
        if (!empty($errors)) {
            api_error(implode(' ', $errors), 422);
        }

        $slug = api_slugify($name);
        $newId = Category::create($name, $slug, $description);
        api_json(['data' => Category::find($newId), 'message' => 'Category created.'], 201);
        break;

    case 'PUT':
        api_require_admin();
        api_require_csrf();

        if ($id <= 0 || !Category::find($id)) {
            api_error('Category not found.', 404);
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        $errors = [];
        if ($name === '') $errors[] = 'Name is required.';
        if (strlen($name) > 100) $errors[] = 'Name must be under 100 characters.';
        if (!empty($errors)) {
            api_error(implode(' ', $errors), 422);
        }

        Category::update($id, $name, api_slugify($name), $description);
        api_json(['data' => Category::find($id), 'message' => 'Category updated.']);
        break;

    case 'DELETE':
        api_require_admin();
        api_require_csrf();

        if ($id <= 0 || !($category = Category::find($id))) {
            api_error('Category not found.', 404);
        }
        $inUse = Category::movieCount($id);
        if ($inUse > 0) {
            api_error("Can't delete \"{$category['name']}\" — {$inUse} title(s) still use it.", 409);
        }
        Category::delete($id);
        api_json(['message' => 'Category deleted.']);
        break;

    default:
        api_error('Method not allowed.', 405);
}
