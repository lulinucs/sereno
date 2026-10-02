<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = require __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/storage.php';
require_once __DIR__ . '/src/alert_engine.php';
require_once __DIR__ . '/src/alert_persistence.php';

try {
    $generated = alert_with_lock(
        alert_paths($config),
        static function (array &$states, array &$events) use ($config): array {
            // A consulta e a transição compartilham a mesma transação de escrita.
            $latestByDevice = storage_latest_all($config['db']);

            $created = [];
            $now = new DateTimeImmutable('now');
            foreach ($config['devices'] as $deviceId => $device) {
                $created = array_merge(
                    $created,
                    alert_apply_offline($states, $events, $deviceId, $device, $latestByDevice[$deviceId] ?? null, $now)
                );
            }
            return $created;
        }
    );

    echo 'Monitor concluído: ' . count($generated) . " evento(s) gerado(s).\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha no monitor: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
