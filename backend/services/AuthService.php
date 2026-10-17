<?php
/**
 * CineNova — Unified Authentication service.
 * ---------------------------------------------------------------
 * ONE login system for the whole site. Both regular users and the
 * admin sign in through the same backend/api/auth.php, against the
 * same storage/users.json store — what they can do afterward is
 * decided purely by their `role` ('user' | 'admin'), checked fresh
 * on every request. There is no separate admin credential system.
 */
declare(strict_types=1);

if (!defined('CINENOVA_DATA_DIR')) {
    define('CINENOVA_DATA_DIR', __DIR__ . '/../storage');
}
define('AUTH_SESSION_NAME', 'cinenova_sid');
define('AUTH_IDLE_TIMEOUT', 12 * 60 * 60); // 12 hours
define('AUTH_MAX_LOGIN_ATTEMPTS', 5);
define('AUTH_LOCKOUT_SECONDS', 5 * 60);

// ============================================================
// Session bootstrap
// ============================================================
function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name(AUTH_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $isHttps,
    ]);
    session_start();
}

// ============================================================
// User store (storage/users.json) — read/write with file locking
// ============================================================
function auth_read_users(): array
{
    $path = CINENOVA_DATA_DIR . '/users.json';
    if (!is_file($path)) {
        return [];
    }
    $fp = fopen($path, 'r');
    if ($fp === false) return [];
    flock($fp, LOCK_SH);
    $contents = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode((string) $contents, true);
    return is_array($data) ? $data : [];
}

function auth_write_users(array $users): bool
{
    $path = CINENOVA_DATA_DIR . '/users.json';
    $fp = fopen($path, 'c+');
    if ($fp === false) return false;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
    ftruncate($fp, 0);
    rewind($fp);
    $json = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ok = $json !== false && fwrite($fp, $json) !== false;
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $ok;
}

function auth_find_user_by_username(string $username): ?array
{
    foreach (auth_read_users() as $u) {
        if (hash_equals(strtolower($u['username'] ?? ''), strtolower($username))) {
            return $u;
        }
    }
    return null;
}

function auth_find_user_by_email(string $email): ?array
{
    foreach (auth_read_users() as $u) {
        if (hash_equals(strtolower($u['email'] ?? ''), strtolower($email))) {
            return $u;
        }
    }
    return null;
}

function auth_find_user_by_id(int $id): ?array
{
    foreach (auth_read_users() as $u) {
        if ((int) ($u['id'] ?? 0) === $id) {
            return $u;
        }
    }
    return null;
}

// ============================================================
// Registration
// ============================================================
function auth_register(string $username, string $email, string $password): array
{
    $username = trim($username);
    $email = trim($email);
    $errors = [];

    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors[] = 'Username must be 3–20 characters: letters, numbers, and underscores only.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (empty($errors) && auth_find_user_by_username($username)) {
        $errors[] = 'That username is already taken.';
    }
    if (empty($errors) && auth_find_user_by_email($email)) {
        $errors[] = 'An account with that email already exists.';
    }
    if (!empty($errors)) {
        return ['ok' => false, 'errors' => $errors];
    }

    $users = auth_read_users();
    $maxId = 0;
    foreach ($users as $u) { $maxId = max($maxId, (int) ($u['id'] ?? 0)); }

    $newUser = [
        'id' => $maxId + 1,
        'username' => $username,
        'name' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'user',
        'plan' => 'Free',
        'status' => 'active',
        'joined' => date('Y-m-d'),
    ];
    $users[] = $newUser;
    auth_write_users($users);

    return ['ok' => true, 'user' => $newUser];
}

// ============================================================
// Login / session state
// ============================================================
function auth_lockout_remaining(): int
{
    auth_start_session();
    $until = (int) ($_SESSION['auth_lockout_until'] ?? 0);
    return $until > time() ? $until - time() : 0;
}

function auth_register_failed_login(): void
{
    auth_start_session();
    $attempts = (int) ($_SESSION['auth_failed_attempts'] ?? 0) + 1;
    $_SESSION['auth_failed_attempts'] = $attempts;
    if ($attempts >= AUTH_MAX_LOGIN_ATTEMPTS) {
        $_SESSION['auth_lockout_until'] = time() + AUTH_LOCKOUT_SECONDS;
        $_SESSION['auth_failed_attempts'] = 0;
    }
}

function auth_clear_failed_logins(): void
{
    auth_start_session();
    unset($_SESSION['auth_failed_attempts'], $_SESSION['auth_lockout_until']);
}

function auth_attempt_login(string $username, string $password): ?array
{
    $user = auth_find_user_by_username(trim($username));

    if ($user && ($user['status'] ?? 'active') === 'active' && password_verify($password, $user['password_hash'] ?? '')) {
        auth_start_session();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = $user['role'] ?? 'user';
        $_SESSION['last_seen'] = time();
        auth_clear_failed_logins();
        return $user;
    }

    auth_register_failed_login();
    return null;
}

function auth_login_user_record(array $user): void
{
    auth_start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'] ?? 'user';
    $_SESSION['last_seen'] = time();
}

function auth_current_user(): ?array
{
    auth_start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $lastSeen = (int) ($_SESSION['last_seen'] ?? 0);
    if ($lastSeen > 0 && (time() - $lastSeen) > AUTH_IDLE_TIMEOUT) {
        auth_logout();
        return null;
    }
    $_SESSION['last_seen'] = time();

    $user = auth_find_user_by_id((int) $_SESSION['user_id']);
    if (!$user || ($user['status'] ?? 'active') !== 'active') {
        auth_logout();
        return null;
    }
    return $user;
}

function auth_is_logged_in(): bool
{
    return auth_current_user() !== null;
}

function auth_is_admin(): bool
{
    $user = auth_current_user();
    return $user !== null && ($user['role'] ?? 'user') === 'admin';
}

function auth_logout(): void
{
    auth_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/** Every URL this is called with must be a same-site path (open-redirect guard). */
function auth_safe_return_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || strpos($url, '//') === 0 || preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
        return '';
    }
    return $url;
}

// ============================================================
// CSRF protection — shared by every form/API write site-wide
// ============================================================
function auth_csrf_token(): string
{
    auth_start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function auth_csrf_valid(?string $token): bool
{
    auth_start_session();
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/** Flash-message helpers (one-time banners shown by the admin UI after an action). */
function auth_set_flash(string $type, string $message): void
{
    auth_start_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function auth_get_flash(): ?array
{
    auth_start_session();
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}
