<?php
/**
 * CineNova API — authentication.
 * ---------------------------------------------------------------
 * GET  /backend/api/auth.php?action=me           -> current session (or null)
 * GET  /backend/api/auth.php?action=csrf          -> {csrf_token}
 * POST /backend/api/auth.php  action=login         {username, password}
 * POST /backend/api/auth.php  action=signup        {username, email, password, confirm_password}
 * POST /backend/api/auth.php  action=logout
 *
 * One login system for both regular users and the admin — see
 * backend/services/AuthService.php. The frontend never decides who
 * is an admin; it only ever reflects back what this endpoint says.
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

auth_start_session();

function auth_public_user(array $user): array
{
    return [
        'id'       => (int) $user['id'],
        'username' => $user['username'] ?? '',
        'name'     => $user['name'] ?? ($user['username'] ?? ''),
        'email'    => $user['email'] ?? '',
        'role'     => $user['role'] ?? 'user',
        'plan'     => $user['plan'] ?? 'Free',
        'joined'   => $user['joined'] ?? null,
    ];
}

$method = api_resolve_method();
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

if ($method === 'GET' && $action === 'csrf') {
    api_json(['success' => true, 'data' => ['csrf_token' => auth_csrf_token()]]);
}

if ($method === 'GET' && ($action === 'me' || $action === '')) {
    $user = auth_current_user();
    api_json(['success' => true, 'data' => ['authenticated' => $user !== null, 'user' => $user ? auth_public_user($user) : null]]);
}

if ($method !== 'POST') {
    api_error('Method not allowed.', 405);
}

api_require_csrf();

if ($action === 'login') {
    $remaining = auth_lockout_remaining();
    if ($remaining > 0) {
        api_error("Too many failed attempts. Try again in {$remaining}s.", 429);
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        api_error('Username and password are required.', 422);
    }

    $user = auth_attempt_login($username, $password);
    if (!$user) {
        api_error('Invalid username or password.', 401);
    }

    $returnUrl = auth_safe_return_url((string) ($_POST['return_url'] ?? ''));
    api_json(['success' => true, 'data' => ['user' => auth_public_user($user), 'return_url' => $returnUrl], 'message' => 'Welcome back!']);
}

if ($action === 'signup') {
    $username = (string) ($_POST['username'] ?? '');
    $email = (string) ($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($password !== $confirm) {
        api_error('Passwords do not match.', 422);
    }

    $result = auth_register($username, $email, $password);
    if (!$result['ok']) {
        api_error(implode(' ', $result['errors']), 422, ['errors' => $result['errors']]);
    }

    auth_login_user_record($result['user']);
    api_json(['success' => true, 'data' => ['user' => auth_public_user($result['user'])], 'message' => 'Account created — welcome to CineNova!']);
}

if ($action === 'logout') {
    auth_logout();
    api_json(['success' => true, 'message' => 'Logged out.']);
}

if ($action === 'update_profile') {
    $me = api_require_login();

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $errors = [];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($newPassword !== '' && strlen($newPassword) < 8) $errors[] = 'New password must be at least 8 characters.';
    if ($newPassword !== '' && !password_verify($currentPassword, $me['password_hash'] ?? '')) {
        $errors[] = 'Your current password is incorrect.';
    }

    $users = auth_read_users();
    foreach ($users as $u) {
        if ((int) $u['id'] !== (int) $me['id'] && strcasecmp((string) $u['email'], $email) === 0) {
            $errors[] = 'That email is already used by another account.';
        }
    }

    if (!empty($errors)) {
        api_error(implode(' ', $errors), 422, ['errors' => $errors]);
    }

    $updated = $me;
    foreach ($users as &$u) {
        if ((int) $u['id'] === (int) $me['id']) {
            $u['name'] = $name !== '' ? $name : $u['name'];
            $u['email'] = $email;
            if ($newPassword !== '') {
                $u['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            $updated = $u;
            break;
        }
    }
    unset($u);
    auth_write_users($users);

    api_json(['success' => true, 'data' => ['user' => auth_public_user($updated)], 'message' => 'Your profile was updated.']);
}

api_error('Unknown action.', 400);
