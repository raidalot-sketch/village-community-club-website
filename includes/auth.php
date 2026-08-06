<?php
/**
 * Admin authentication, session management, and role-based access control.
 *
 * Roles: 'administrator' (full access) and 'editor' (content only — cannot
 * delete administrators, manage users, or change site settings).
 */

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_MINUTES = 15;

/** Returns the logged-in admin's row, or null if not logged in. */
function current_admin(): ?array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached ?: null;
    }
    if (empty($_SESSION['admin_id'])) {
        $cached = false;
        return null;
    }
    $stmt = db()->prepare('SELECT id, username, email, full_name, role, is_active FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    if (!$admin || !$admin['is_active']) {
        auth_logout();
        $cached = false;
        return null;
    }
    $cached = $admin;
    return $admin;
}

function is_logged_in(): bool
{
    return current_admin() !== null;
}

function is_administrator(): bool
{
    $admin = current_admin();
    return $admin && $admin['role'] === 'administrator';
}

/** Call at the top of any admin page that requires login. */
function require_login(): void
{
    if (!is_logged_in()) {
        redirect('/admin/login.php');
    }
}

/** Call at the top of any admin page restricted to full administrators. */
function require_administrator(): void
{
    require_login();
    if (!is_administrator()) {
        http_response_code(403);
        die('You do not have permission to access this page. This area is restricted to Administrators.');
    }
}

// ---------------------------------------------------------------------
// Login rate limiting
// ---------------------------------------------------------------------

function login_attempts_remaining(string $identifier, string $ip): int
{
    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM login_attempts
        WHERE (identifier = :identifier OR ip_address = :ip)
        AND success = 0
        AND attempted_at > (NOW() - INTERVAL :mins MINUTE)');
    $stmt->bindValue('identifier', $identifier);
    $stmt->bindValue('ip', $ip);
    $stmt->bindValue('mins', LOGIN_LOCKOUT_MINUTES, PDO::PARAM_INT);
    $stmt->execute();
    $count = (int) $stmt->fetch()['c'];
    return max(0, LOGIN_MAX_ATTEMPTS - $count);
}

function record_login_attempt(string $identifier, string $ip, bool $success): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (identifier, ip_address, success, attempted_at)
        VALUES (:identifier, :ip, :success, NOW())');
    $stmt->execute(['identifier' => $identifier, 'ip' => $ip, 'success' => $success ? 1 : 0]);
}

/**
 * Attempt to log an admin in. Returns true on success, false on failure.
 * Handles rate limiting, password verification, and session regeneration.
 */
function attempt_login(string $identifier, string $password): array
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $remaining = login_attempts_remaining($identifier, $ip);

    if ($remaining <= 0) {
        return ['success' => false, 'message' => 'Too many failed login attempts. Please wait ' . LOGIN_LOCKOUT_MINUTES . ' minutes and try again.'];
    }

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE (username = :u OR email = :u) LIMIT 1');
    $stmt->execute(['u' => $identifier]);
    $user = $stmt->fetch();

    if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
        record_login_attempt($identifier, $ip, false);
        $remaining--;
        $msg = $remaining > 0
            ? 'Incorrect username/email or password. ' . $remaining . ' attempt(s) remaining before a temporary lockout.'
            : 'Incorrect username/email or password. Too many failed attempts — please wait ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
        return ['success' => false, 'message' => $msg];
    }

    // Rehash transparently if PHP's default algorithm/cost has changed.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        db()->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id')
            ->execute(['h' => $newHash, 'id' => $user['id']]);
    }

    record_login_attempt($identifier, $ip, true);
    auth_login($user);

    return ['success' => true, 'message' => 'Logged in successfully.'];
}

/** Establish a logged-in session for the given admin row. Regenerates the session ID. */
function auth_login(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_role'] = $user['role'];
    $_SESSION['last_activity'] = time();

    db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]);
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------
// Password reset
// ---------------------------------------------------------------------

/** Creates a password reset token for the given email and returns the raw (unhashed) token, or null if no such account. */
function create_password_reset_token(string $email): ?string
{
    $stmt = db()->prepare('SELECT id FROM admin_users WHERE email = :email AND is_active = 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);

    db()->prepare('DELETE FROM password_resets WHERE admin_id = :id')->execute(['id' => $user['id']]);
    db()->prepare('INSERT INTO password_resets (admin_id, token_hash, expires_at, created_at)
        VALUES (:id, :hash, NOW() + INTERVAL 1 HOUR, NOW())')
        ->execute(['id' => $user['id'], 'hash' => $hash]);

    return $token;
}

function verify_password_reset_token(string $token): ?int
{
    $hash = hash('sha256', $token);
    $stmt = db()->prepare('SELECT admin_id FROM password_resets WHERE token_hash = :hash AND expires_at > NOW()');
    $stmt->execute(['hash' => $hash]);
    $row = $stmt->fetch();
    return $row ? (int) $row['admin_id'] : null;
}

function consume_password_reset_token(string $token, string $newPassword): bool
{
    $adminId = verify_password_reset_token($token);
    if (!$adminId) {
        return false;
    }
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    db()->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id')->execute(['h' => $hash, 'id' => $adminId]);
    db()->prepare('DELETE FROM password_resets WHERE admin_id = :id')->execute(['id' => $adminId]);
    return true;
}
