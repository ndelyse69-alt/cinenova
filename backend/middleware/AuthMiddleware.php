<?php
/**
 * CineNova — Auth middleware.
 * ---------------------------------------------------------------
 * Reusable request guards for backend/api/* endpoints. Pages are now
 * static HTML, so there is nothing server-side left to "redirect" —
 * the frontend JS calls the relevant API first (e.g. GET
 * /backend/api/auth.php?action=me) and redirects client-side if the
 * response says the visitor isn't authorized. These guards are what
 * that check (and every write endpoint) relies on server-side, since
 * the frontend's own gate is only a UX convenience, never the real
 * security boundary.
 */
declare(strict_types=1);

/** Every mutating/protected request must come from a logged-in user. */
function api_require_login(): array
{
    $user = auth_current_user();
    if (!$user) {
        Response::error('Authentication required.', 401);
    }
    return $user;
}

/** Every mutating admin request must come from a logged-in admin — checked server-side, never trusted from the frontend. */
function api_require_admin(): array
{
    $user = api_require_login();
    if (($user['role'] ?? 'user') !== 'admin') {
        Response::error('Admin access required.', 403);
    }
    return $user;
}

/** CSRF check for API writes — same token/session mechanism as every other form in the app. */
function api_require_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!auth_csrf_valid($token)) {
        Response::error('Invalid or missing CSRF token.', 419);
    }
}
