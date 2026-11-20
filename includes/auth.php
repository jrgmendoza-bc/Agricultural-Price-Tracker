<?php
/**
 * auth.php - session management, role verification
 * and server-side login throttling.
 */

class Auth
{
    private static ?array $user = null;
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_name('agripri_track_session');
        session_start();
        self::$started = true;
    }

    /** Verify credentials (username or email + password). */
    public static function attempt(string $identifier, string $password): ?array
    {
        $identifier = trim($identifier);
        $password = trim($password);
        $user = Database::first(
            "SELECT * FROM users
              WHERE (email = :email OR username = :username) AND deleted_at IS NULL
              LIMIT 1",
            [':email' => $identifier, ':username' => $identifier]
        );
        if ($user && isset($user['password']) && password_verify($password, $user['password'])) {
            return $user;
        }
        return null;
    }

    /** Establish a session for a verified user (regenerates the session id). */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']    = (int)$user['id'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['login_time'] = time();
        self::$user = $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$user = null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function role(): ?string
    {
        return $_SESSION['role'] ?? null;
    }

    /** Currently authenticated user row (soft-delete aware), cached per request. */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        if (self::$user === null) {
            $user = Database::first(
                "SELECT * FROM users WHERE id = ? AND deleted_at IS NULL",
                [self::id()]
            );
            if ($user === null) {
                self::logout();
                return null;
            }
            self::$user = $user;
        }
        return self::$user;
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if ($user === null) {
            flash('Please sign in to continue.', 'error');
            redirect('/login.php');
        }
        return $user;
    }

    /** Role-based access control: only the listed roles may enter. */
    public static function requireRole(string ...$roles): array
    {
        $user = self::requireLogin();
        if (!in_array($user['role'], $roles, true)) {
            http_response_code(403);
            exit('403 - Access denied. Your account role is not allowed on this page.');
        }
        return $user;
    }

    /** Dashboard route for a role (RBAC routing after login). */
    public static function dashboardUrl(string $role): string
    {
        return match ($role) {
            'admin'  => '/dashboard/admin/overview.php',
            'buyer'  => '/dashboard/buyer/overview.php',
            'farmer' => '/dashboard/client/overview.php',
            default  => '/index.php',
        };
    }

    public static function displayName(array $user): string
    {
        return trim($user['first_name'] . ' ' . $user['last_name']);
    }

    public static function initials(array $user): string
    {
        return strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
    }
}

/**
 * Server-side login throttling:
 * 5 failed attempts per identifier + IP locks that identifier for 15 minutes.
 */
class LoginThrottle
{
    private const MAX_ATTEMPTS  = 5;
    private const WINDOW_MINUTES = 15;

    public static function isLocked(string $identifier, string $ip): bool
    {
        $row = Database::first(
            "SELECT attempts, last_attempt_at FROM login_attempts
              WHERE identifier = ? AND ip_address = ?",
            [$identifier, $ip]
        );
        if (!$row) {
            return false;
        }
        $windowed = strtotime($row['last_attempt_at']) > (time() - self::WINDOW_MINUTES * 60);
        return $windowed && (int)$row['attempts'] >= self::MAX_ATTEMPTS;
    }

    public static function recordFailure(string $identifier, string $ip): void
    {
        Database::execute(
            "INSERT INTO login_attempts (identifier, ip_address, attempts, last_attempt_at)
             VALUES (?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE
               attempts = IF(last_attempt_at > NOW() - INTERVAL 15 MINUTE, attempts + 1, 1),
               last_attempt_at = NOW()",
            [$identifier, $ip]
        );
    }

    public static function clear(string $identifier, string $ip): void
    {
        Database::execute(
            "DELETE FROM login_attempts WHERE identifier = ? AND ip_address = ?",
            [$identifier, $ip]
        );
    }

    public static function secondsRemaining(string $identifier, string $ip): int
    {
        $row = Database::first(
            "SELECT last_attempt_at FROM login_attempts
              WHERE identifier = ? AND ip_address = ? AND attempts >= ?",
            [$identifier, $ip, self::MAX_ATTEMPTS]
        );
        if (!$row) {
            return 0;
        }
        $unlock = strtotime($row['last_attempt_at']) + self::WINDOW_MINUTES * 60;
        return max(0, $unlock - time());
    }
}


