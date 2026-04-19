<?php
/**
 * Session-based admin auth.
 *
 * Drop-in replacement for the earlier HTTP Basic gate.
 * Reads ADMIN_USER / ADMIN_PASS from .env, compares with hash_equals,
 * installs a session flag, issues a CSRF token, and tracks failed attempts
 * per session (soft lockout after 6 failures).
 */
declare(strict_types=1);

namespace RareFolio;

require_once __DIR__ . '/../../src/Config.php';

final class Auth
{
    private const SESSION_KEY   = 'rf_admin';
    private const LOCKOUT_AFTER = 6;   // failed attempts before lockout
    private const LOCKOUT_SECS  = 300; // 5 minutes

    /**
     * Admin credentials come exclusively from the environment
     * (`.env` -> ADMIN_USER / ADMIN_PASS, read via Config::get()).
     *
     * There is intentionally NO in-source fallback. If either value is
     * missing or empty at runtime, `attempt()` fails closed so that a
     * missing/clobbered .env cannot silently downgrade to a guessable
     * credential baked into the codebase. An operator alert (error_log)
     * is emitted so the condition is visible in server logs.
     *
     * Deploy contract: ship a populated .env alongside every deploy.
     */

    public static function boot(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('rf_admin_sess');
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']),
            ]);
            session_start();
        }
    }

    public static function isLoggedIn(): bool
    {
        self::boot();
        return !empty($_SESSION[self::SESSION_KEY]['user']);
    }

    public static function currentUser(): ?string
    {
        self::boot();
        return $_SESSION[self::SESSION_KEY]['user'] ?? null;
    }

    /**
     * Require the user to be logged in. If not, redirect to /admin/login.php.
     */
    public static function requireLogin(): void
    {
        self::boot();
        if (self::isLoggedIn()) return;
        $target = $_SERVER['REQUEST_URI'] ?? '/admin/';
        header('Location: /admin/login.php?next=' . urlencode($target));
        exit;
    }

    public static function csrfToken(): string
    {
        self::boot();
        if (empty($_SESSION[self::SESSION_KEY]['csrf'])) {
            $_SESSION[self::SESSION_KEY]['csrf'] = bin2hex(random_bytes(16));
        }
        return (string) $_SESSION[self::SESSION_KEY]['csrf'];
    }

    public static function checkCsrf(?string $submitted): bool
    {
        self::boot();
        $want = $_SESSION[self::SESSION_KEY]['csrf'] ?? '';
        return is_string($submitted) && $submitted !== '' && hash_equals($want, $submitted);
    }

    public static function lockedOutUntil(): ?int
    {
        self::boot();
        $until = (int) ($_SESSION[self::SESSION_KEY]['lockout_until'] ?? 0);
        return $until > time() ? $until : null;
    }

    public static function attempt(string $user, string $pass): bool
    {
        self::boot();

        if (($until = self::lockedOutUntil()) !== null) {
            return false;
        }

        // Credentials come strictly from the environment. No source-code
        // fallback: if either is missing we fail closed so a wiped .env
        // cannot enable login with a hardcoded value.
        $expectedUser = (string) Config::get('ADMIN_USER', '');
        $expectedPass = (string) Config::get('ADMIN_PASS', '');
        if ($expectedUser === '' || $expectedPass === '') {
            error_log('[auth.php] ADMIN_USER or ADMIN_PASS missing in environment; admin login is fail-closed until .env is restored.');
            return false;
        }

        $ok = hash_equals($expectedUser, $user)
            && hash_equals($expectedPass, $pass);

        if ($ok) {
            // Reset session state, elevate.
            session_regenerate_id(true);
            $_SESSION[self::SESSION_KEY] = [
                'user'          => $user,
                'logged_in_at'  => time(),
                'csrf'          => bin2hex(random_bytes(16)),
                'failures'      => 0,
            ];
            return true;
        }

        $failures = (int) ($_SESSION[self::SESSION_KEY]['failures'] ?? 0) + 1;
        $_SESSION[self::SESSION_KEY]['failures'] = $failures;
        if ($failures >= self::LOCKOUT_AFTER) {
            $_SESSION[self::SESSION_KEY]['lockout_until'] = time() + self::LOCKOUT_SECS;
        }
        return false;
    }

    public static function logout(): void
    {
        self::boot();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
