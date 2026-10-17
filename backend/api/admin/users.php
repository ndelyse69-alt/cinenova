<?php
/**
 * CineNova Admin API — users.
 * ---------------------------------------------------------------
 * GET    /backend/api/admin/users.php                 -> list
 * POST   /backend/api/admin/users.php action=update    {id, role?, plan?, status?}
 * POST   /backend/api/admin/users.php action=delete    {id}
 *
 * Backs Admin → Users. An admin can promote/demote roles, change
 * plan, and suspend/reactivate an account — but never edits a raw
 * password hash here (users manage their own password via Profile).
 */
declare(strict_types=1);

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../_helpers.php';

$currentAdmin = api_require_admin();

$method = api_resolve_method();
$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

if ($method === 'GET') {
    $users = array_map(function ($u) {
        unset($u['password_hash']);
        return $u;
    }, auth_read_users());
    api_json(['success' => true, 'data' => ['users' => $users]]);
}

if ($method !== 'POST') {
    api_error('Method not allowed.', 405);
}
api_require_csrf();

$users = auth_read_users();
$id = (int) ($_POST['id'] ?? 0);

if ($action === 'update') {
    if ($id === (int) $currentAdmin['id'] && ($_POST['role'] ?? '') !== 'admin') {
        api_error('You cannot remove your own admin role.', 422);
    }

    $found = false;
    foreach ($users as &$u) {
        if ((int) $u['id'] === $id) {
            if (isset($_POST['role']) && in_array($_POST['role'], ['user', 'admin'], true)) {
                $u['role'] = $_POST['role'];
            }
            if (isset($_POST['plan']) && trim((string) $_POST['plan']) !== '') {
                $u['plan'] = trim((string) $_POST['plan']);
            }
            if (isset($_POST['status']) && in_array($_POST['status'], ['active', 'suspended'], true)) {
                $u['status'] = $_POST['status'];
            }
            $found = true;
            break;
        }
    }
    unset($u);
    if (!$found) {
        api_error('User not found.', 404);
    }
    auth_write_users($users);
    api_json(['success' => true, 'message' => 'User updated.']);
}

if ($action === 'delete') {
    if ($id === (int) $currentAdmin['id']) {
        api_error('You cannot delete your own account.', 422);
    }
    $users = array_values(array_filter($users, fn($u) => (int) $u['id'] !== $id));
    auth_write_users($users);
    api_json(['success' => true, 'message' => 'User removed.']);
}

api_error('Unknown action.', 400);
