<?php

declare(strict_types=1);

/**
 * Carrega um .env simples, suficiente para este projeto e sem dependencias.
 * Variaveis ja definidas no ambiente do servidor tem precedencia.
 */
function load_env(string $file): void
{
    if (!is_file($file) || !is_readable($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key) || getenv($key) !== false) {
            continue;
        }

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

load_env(dirname(__DIR__) . '/.env');

function project_path(string $path): string
{
    if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path)) {
        return $path;
    }

    return dirname(__DIR__) . '/' . ltrim($path, '/\\');
}

require_once __DIR__ . '/devices.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/device_repository.php';
$databaseFile = project_path((string) env_value('SQLITE_FILE', 'data/camara.sqlite'));
$db = db_connect($databaseFile);
$devices = device_db_load($db);
foreach ($devices as $deviceId => &$device) {
    $tokenKey = 'DEVICE_' . strtoupper(str_replace('-', '_', $deviceId)) . '_TOKEN';
    $device['token'] = (string) env_value($tokenKey, '');
}
unset($device);

return [
    'app_url' => rtrim((string) env_value('APP_URL', ''), '/'),
    'database_file' => $databaseFile,
    'db' => $db,
    'default_device' => (string) env_value('DEFAULT_DEVICE', 'camara-01'),
    'admin_password_hash' => (string) env_value('ADMIN_PASSWORD_HASH', ''),
    'telegram' => [
        'bot_token' => (string) env_value('TELEGRAM_BOT_TOKEN', ''),
        'chat_id' => (string) env_value('TELEGRAM_CHAT_ID', ''),
    ],
    'devices' => $devices,
];
