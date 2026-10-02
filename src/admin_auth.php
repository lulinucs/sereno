<?php
declare(strict_types=1);

const ADMIN_IDLE_SECONDS = 8 * 60 * 60;
const ADMIN_LOGIN_WINDOW_SECONDS = 15 * 60;
const ADMIN_LOGIN_LOCK_SECONDS = 5 * 60;
const ADMIN_LOGIN_MAX_FAILURES = 5;

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function admin_ensure_csrf(): string
{
    admin_session_start();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function admin_csrf_valid(string $received): bool
{
    admin_session_start();
    $expected = $_SESSION['csrf_token'] ?? '';
    return is_string($expected) && $expected !== '' && $received !== '' && hash_equals($expected, $received);
}

function admin_authenticated(bool $touch = false): bool
{
    admin_session_start();
    $last = $_SESSION['admin_last_activity'] ?? null;
    if (($last === null) || !is_int($last)) return false;
    if (time() - $last > ADMIN_IDLE_SECONDS) {
        unset($_SESSION['admin_last_activity']);
        session_regenerate_id(true);
        return false;
    }
    if ($touch) $_SESSION['admin_last_activity'] = time();
    return true;
}

function admin_client_key(string $address): string
{
    return hash('sha256', $address);
}

function admin_rate_limited(PDO $db, string $key, int $now): bool
{
    $row = db_execute($db, 'SELECT locked_until FROM admin_login_attempts WHERE client_key=?', [$key])->fetch();
    return $row && (int) $row['locked_until'] > $now;
}

function admin_record_failure(PDO $db, string $key, int $now): void
{
    db_transaction($db, static function () use ($db, $key, $now): void {
        $row = db_execute($db, 'SELECT attempts,window_started FROM admin_login_attempts WHERE client_key=?', [$key])->fetch();
        $fresh = !$row || $now - (int) $row['window_started'] >= ADMIN_LOGIN_WINDOW_SECONDS;
        $started = $fresh ? $now : (int) $row['window_started'];
        $attempts = $fresh ? 1 : (int) $row['attempts'] + 1;
        $lockedUntil = $attempts >= ADMIN_LOGIN_MAX_FAILURES ? $now + ADMIN_LOGIN_LOCK_SECONDS : 0;
        db_execute($db, 'INSERT INTO admin_login_attempts(client_key,attempts,window_started,locked_until) VALUES (?,?,?,?) ON CONFLICT(client_key) DO UPDATE SET attempts=excluded.attempts,window_started=excluded.window_started,locked_until=excluded.locked_until', [$key, $attempts, $started, $lockedUntil]);
    });
}

function admin_login(PDO $db, string $password, string $hash, string $address): string
{
    if ($hash === '' || password_get_info($hash)['algo'] === null) return 'unconfigured';
    $key = admin_client_key($address);
    $now = time();
    if (admin_rate_limited($db, $key, $now)) return 'locked';
    if (!password_verify($password, $hash)) {
        admin_record_failure($db, $key, $now);
        return 'invalid';
    }
    db_execute($db, 'DELETE FROM admin_login_attempts WHERE client_key=?', [$key]);
    admin_session_start();
    if (!session_regenerate_id(true)) throw new RuntimeException('Falha ao iniciar sessao administrativa.');
    $_SESSION['admin_last_activity'] = $now;
    return 'ok';
}

function admin_logout(): void
{
    admin_session_start();
    unset($_SESSION['admin_last_activity']);
    session_regenerate_id(true);
}
