<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/database.php';
require_once dirname(__DIR__) . '/src/admin_auth.php';

$db = db_connect(':memory:', true);
db_schema($db);
$_SERVER['HTTPS'] = 'on';
session_id(bin2hex(random_bytes(16)));
admin_session_start();
$count = 0;
function auth_check(bool $ok, string $message): void { global $count; $count++; if (!$ok) throw new RuntimeException($message); }

try {
    $cookie = session_get_cookie_params();
    auth_check($cookie['httponly'] && $cookie['secure'] && $cookie['samesite'] === 'Strict', 'cookie HTTPS');
    auth_check(strlen(admin_ensure_csrf()) === 64, 'CSRF criado');
    auth_check(!admin_authenticated(), 'sessao publica');
    $hash = password_hash('senha-de-teste', PASSWORD_DEFAULT);
    auth_check(admin_login($db, 'errada', $hash, '127.0.0.1') === 'invalid', 'senha invalida');
    $before = session_id();
    auth_check(admin_login($db, 'senha-de-teste', $hash, '127.0.0.1') === 'ok', 'login');
    auth_check(session_id() !== $before && admin_authenticated(), 'sessao regenerada');
    $_SESSION['admin_last_activity'] = time() - ADMIN_IDLE_SECONDS - 1;
    auth_check(!admin_authenticated(), 'timeout');
    auth_check(!isset($_SESSION['admin_last_activity']), 'sessao expirada removida');
    $key = admin_client_key('192.0.2.1');
    for ($i = 0; $i < ADMIN_LOGIN_MAX_FAILURES; $i++) admin_record_failure($db, $key, 1000 + $i);
    auth_check(admin_rate_limited($db, $key, 1005), 'bloqueio temporario');
    auth_check(!admin_rate_limited($db, $key, 2000), 'desbloqueio');
    auth_check(admin_csrf_valid($_SESSION['csrf_token']) && !admin_csrf_valid('incorreto'), 'CSRF preservado');
    echo 'OK - ' . $count . " asserções de sessão, timeout e rate limit.\n";
} finally {
    session_destroy();
}
