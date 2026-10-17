<?php
/**
 * CineNova API — shared helpers
 * Included by every file in backend/api/. Assumes backend/core/bootstrap.php
 * has already been required by the caller (auth, CSRF and admin guards live
 * in backend/middleware/AuthMiddleware.php; response shaping lives in
 * backend/core/Response.php — this file only adds the small bits specific
 * to the REST-ish API layer itself).
 */
declare(strict_types=1);

/**
 * Plain HTML forms can only send GET/POST, so mutating routes accept
 * a `_method` override (POST + _method=PUT/DELETE) the same way
 * Laravel's own forms do — this isn't a framework dependency, just
 * the same well-known convention, hand-rolled.
 */
function api_resolve_method(): string
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST') {
        $override = strtoupper((string) ($_POST['_method'] ?? $_GET['_method'] ?? ''));
        if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
            return $override;
        }
    }
    return $method;
}

/** Back-compat thin wrappers around core/Response.php's envelope helpers. */
function api_json(array $payload, int $status = 200): void
{
    Response::json($payload, $status);
}

function api_error(string $message, int $status = 400, array $extra = []): void
{
    Response::error($message, $status, $extra);
}

function api_slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'item-' . substr(bin2hex(random_bytes(3)), 0, 6);
}
