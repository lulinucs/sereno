<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$config = require __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/storage.php';
require_once __DIR__ . '/src/alert_engine.php';
require_once __DIR__ . '/src/alert_persistence.php';
require_once __DIR__ . '/src/notifications.php';
require_once __DIR__ . '/src/admin_auth.php';

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function valid_number($value, float $minimum, float $maximum): bool
{
    return (is_int($value) || is_float($value))
        && is_finite((float) $value)
        && $value >= $minimum
        && $value <= $maximum;
}

function request_header(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return isset($_SERVER[$serverKey]) ? trim((string) $_SERVER[$serverKey]) : '';
}

function json_body(): array
{
    $rawBody = file_get_contents('php://input');
    $body = json_decode($rawBody === false ? '' : $rawBody, true);
    if (!is_array($body) || json_last_error() !== JSON_ERROR_NONE) {
        respond(400, ['ok' => false, 'error' => 'Corpo JSON invalido.']);
    }
    return $body;
}

function require_admin_write(): void
{
    if (!admin_authenticated()) {
        respond(401, ['ok' => false, 'error' => 'Sessão administrativa necessária.']);
    }
    if (!admin_csrf_valid(request_header('X-CSRF-Token'))) {
        respond(403, ['ok' => false, 'error' => 'Token CSRF invalido. Recarregue o dashboard.']);
    }
    admin_authenticated(true);
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

try {
    if ($action === 'auth_status') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Allow: GET');
            respond(405, ['ok' => false, 'error' => 'Use GET.']);
        }
        respond(200, ['ok' => true, 'authenticated' => admin_authenticated()]);
    }

    if ($action === 'login' || $action === 'logout') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Allow: POST');
            respond(405, ['ok' => false, 'error' => 'Use POST.']);
        }
        if (!admin_csrf_valid(request_header('X-CSRF-Token'))) {
            respond(403, ['ok' => false, 'error' => 'Token CSRF invalido. Recarregue o dashboard.']);
        }
        if ($action === 'logout') {
            admin_logout();
            respond(200, ['ok' => true, 'authenticated' => false]);
        }
        $body = json_body();
        $password = $body['password'] ?? null;
        if (!is_string($password) || strlen($password) > 1024) {
            respond(400, ['ok' => false, 'error' => 'Senha inválida.']);
        }
        $result = admin_login($config['db'], $password, $config['admin_password_hash'], (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if ($result === 'unconfigured') respond(503, ['ok' => false, 'error' => 'Autenticação administrativa indisponível.']);
        if ($result === 'locked') {
            header('Retry-After: 300');
            respond(429, ['ok' => false, 'error' => 'Muitas tentativas. Tente novamente em alguns minutos.']);
        }
        if ($result === 'invalid') respond(401, ['ok' => false, 'error' => 'Senha incorreta.']);
        respond(200, ['ok' => true, 'authenticated' => true]);
    }

    if ($action === 'devices') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Allow: GET');
            respond(405, ['ok' => false, 'error' => 'Use GET.']);
        }
        respond(200, ['ok' => true, 'devices' => devices_public($config['devices'])]);
    }

    if ($action === 'overview') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Allow: GET');
            respond(405, ['ok' => false, 'error' => 'Use GET.']);
        }
        $latest = storage_latest_all($config['db']);
        $states = alert_with_lock(
            alert_paths($config),
            static fn (array &$states, array &$events): array => $states,
            false
        );
        $devices = [];
        foreach ($config['devices'] as $id => $device) {
            $devices[$id] = [
                'name' => $device['nome'],
                'latest' => $latest[$id] ?? null,
                'state' => $states[$id]['status'] ?? null,
                'offline_minutes' => $device['offline_minutos'],
            ];
        }
        respond(200, ['ok' => true, 'devices' => $devices]);
    }

    if ($action === 'device_config') {
        $deviceId = isset($_GET['device_id']) ? (string) $_GET['device_id'] : '';
        if (!devices_validate_id($deviceId)) {
            respond(422, ['ok' => false, 'error' => 'device_id invalido.']);
        }
        if (!isset($config['devices'][$deviceId])) {
            respond(404, ['ok' => false, 'error' => 'Dispositivo nao cadastrado.']);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            respond(200, ['ok' => true, 'device_id' => $deviceId, 'config' => devices_public([$deviceId => $config['devices'][$deviceId]])[$deviceId]]);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Allow: GET, POST');
            respond(405, ['ok' => false, 'error' => 'Use GET ou POST.']);
        }
        require_admin_write();
        try {
            $saved = device_db_save_one($config['db'], $deviceId, json_body());
        } catch (InvalidArgumentException $exception) {
            respond(422, ['ok' => false, 'error' => $exception->getMessage()]);
        }
        respond(200, ['ok' => true, 'device_id' => $deviceId, 'config' => $saved]);
    }

    if ($action === 'events') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Allow: GET');
            respond(405, ['ok' => false, 'error' => 'Use GET.']);
        }
        $deviceId = isset($_GET['device_id']) && $_GET['device_id'] !== '' ? (string) $_GET['device_id'] : null;
        if ($deviceId !== null && !isset($config['devices'][$deviceId])) {
            respond(404, ['ok' => false, 'error' => 'Dispositivo nao cadastrado.']);
        }
        $result = notification_list($config, $deviceId, isset($_GET['limit']) ? (int) $_GET['limit'] : 50);
        respond(200, ['ok' => true] + $result);
    }

    if (in_array($action, ['notification_read', 'notifications_read_all', 'test_notification'], true)) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Allow: POST');
            respond(405, ['ok' => false, 'error' => 'Use POST.']);
        }
        require_admin_write();
        $body = json_body();

        if ($action === 'notification_read') {
            $eventId = $body['event_id'] ?? null;
            if (!is_string($eventId)) {
                respond(422, ['ok' => false, 'error' => 'event_id invalido.']);
            }
            if (!notification_mark_read($config, $eventId)) {
                respond(404, ['ok' => false, 'error' => 'Evento nao encontrado.']);
            }
            respond(200, ['ok' => true]);
        }

        $deviceId = $body['device_id'] ?? null;
        if ($deviceId !== null && (!is_string($deviceId) || !isset($config['devices'][$deviceId]))) {
            respond(404, ['ok' => false, 'error' => 'Dispositivo nao cadastrado.']);
        }
        if ($action === 'notifications_read_all') {
            respond(200, ['ok' => true, 'marked' => notification_mark_all_read($config, $deviceId)]);
        }
        if (!is_string($deviceId)) {
            respond(422, ['ok' => false, 'error' => 'device_id obrigatorio.']);
        }
        respond(201, ['ok' => true, 'event' => notification_create_test_event($config, $deviceId)]);
    }

    if ($action === 'push') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Allow: POST');
            respond(405, ['ok' => false, 'error' => 'Use POST com corpo JSON.']);
        }

        $body = json_body();

        $deviceId = $body['device_id'] ?? null;
        if (!is_string($deviceId) || !preg_match('/^[a-z0-9][a-z0-9-]{1,63}$/', $deviceId)) {
            respond(422, ['ok' => false, 'error' => 'device_id invalido.']);
        }
        if (!isset($config['devices'][$deviceId])) {
            respond(404, ['ok' => false, 'error' => 'Dispositivo nao cadastrado.']);
        }

        $expectedToken = (string) ($config['devices'][$deviceId]['token'] ?? '');
        if ($expectedToken !== '' && !hash_equals($expectedToken, request_header('X-Device-Token'))) {
            respond(401, ['ok' => false, 'error' => 'Token do dispositivo invalido.']);
        }

        if (!valid_number($body['temperatura'] ?? null, -50.0, 80.0)) {
            respond(422, ['ok' => false, 'error' => 'temperatura invalida.']);
        }
        if (!valid_number($body['umidade'] ?? null, 0.0, 100.0)) {
            respond(422, ['ok' => false, 'error' => 'umidade invalida.']);
        }
        if (!valid_number($body['pressao'] ?? null, 300.0, 1200.0)) {
            respond(422, ['ok' => false, 'error' => 'pressao invalida.']);
        }

        $reading = [
            'device_id' => $deviceId,
            'timestamp' => date('c'),
            'temperatura' => round((float) $body['temperatura'], 2),
            'umidade' => round((float) $body['umidade'], 2),
            'pressao' => round((float) $body['pressao'], 2),
        ];

        $total = null;
        $alertResult = ['ok' => true, 'events_generated' => 0];
        try {
            // Serializa persistência + estado com o monitor offline para evitar
            // uma transição falsa enquanto uma nova leitura está em processamento.
            $generatedEvents = alert_with_lock(
                alert_paths($config),
                static function (array &$states, array &$events) use ($config, $deviceId, $reading, &$total): array {
                    $total = storage_add($config['db'], $reading);
                    return alert_apply_reading($states, $events, $deviceId, $config['devices'][$deviceId], $reading);
                }
            );
            $alertResult['events_generated'] = count($generatedEvents);
        } catch (Throwable $alertException) {
            error_log('camara-chopp alert engine: ' . $alertException->getMessage());
            // Se o motor falhou antes da persistência, ainda prioriza a telemetria.
            $total = storage_add($config['db'], $reading);
            $alertResult = ['ok' => false, 'events_generated' => 0];
        }

        respond(201, [
            'ok' => true,
            'reading' => $reading,
            'total' => $total,
            'alert_engine' => $alertResult,
        ]);
    }

    if ($action === 'data') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Allow: GET');
            respond(405, ['ok' => false, 'error' => 'Use GET.']);
        }

        $deviceId = isset($_GET['device_id']) && $_GET['device_id'] !== ''
            ? (string) $_GET['device_id']
            : $config['default_device'];

        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,63}$/', $deviceId)) {
            respond(422, ['ok' => false, 'error' => 'device_id invalido.']);
        }
        if (!isset($config['devices'][$deviceId])) {
            respond(404, ['ok' => false, 'error' => 'Dispositivo nao cadastrado.']);
        }

        $hours = isset($_GET['hours']) ? (int) $_GET['hours'] : 24;
        if (!in_array($hours, [1, 6, 24, 168], true)) respond(422, ['ok' => false, 'error' => 'Periodo invalido.']);
        $rows = storage_for_device($config['db'], $deviceId, time() - max(24, $hours) * 3600);
        $latest = storage_latest($config['db'], $deviceId);
        $device = $config['devices'][$deviceId];

        try {
            $currentState = alert_get_state($config, $deviceId);
        } catch (Throwable $alertException) {
            error_log('camara-chopp alert state: ' . $alertException->getMessage());
            $currentState = null;
        }

        respond(200, [
            'ok' => true,
            'device_id' => $deviceId,
            'device_name' => $device['nome'],
            'offline_minutes' => $device['offline_minutos'],
            'latest' => $latest,
            'state' => $currentState,
            'temperature_limits' => [
                'min' => $device['temperatura_min'],
                'max' => $device['temperatura_max'],
            ],
            'events' => array_values(array_filter(
                notification_events($config, $deviceId, time() - $hours * 3600),
                static fn (array $event): bool => in_array($event['type'] ?? '', ['TEMP_HIGH', 'TEMP_LOW', 'OFFLINE', 'ONLINE', 'NORMALIZED'], true)
            )),
            'logs' => $rows,
        ]);
    }

    respond(404, ['ok' => false, 'error' => 'Acao inexistente.']);
} catch (Throwable $exception) {
    error_log('camara-chopp: ' . $exception->getMessage());
    respond(500, ['ok' => false, 'error' => 'Falha interna ao processar a solicitacao.']);
}
