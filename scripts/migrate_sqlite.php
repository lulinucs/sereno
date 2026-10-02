<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/src/database.php';
require_once dirname(__DIR__) . '/src/devices.php';
require_once dirname(__DIR__) . '/src/device_repository.php';
require_once dirname(__DIR__) . '/src/alert_persistence.php';
require_once dirname(__DIR__) . '/src/migration.php';

$root = dirname(__DIR__);
$dataDir = $argv[1] ?? $root . '/data';
$databasePath = $argv[2] ?? $dataDir . '/camara.sqlite';
$defaultDevice = $argv[3] ?? (getenv('DEFAULT_DEVICE') ?: 'camara-01');
try {
    $source = migration_sources($dataDir, $defaultDevice);
    $db = db_connect($databasePath, true);
    db_schema($db);
    $result = migration_import($db, $source);
    echo json_encode(['ok' => true, 'database' => $databasePath, 'verified' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migracao falhou: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
