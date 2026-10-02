<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/src/database.php';

$databasePath = $argv[1] ?? dirname(__DIR__) . '/data/camara.sqlite';

if (is_file($databasePath)) {
    fwrite(STDERR, "Banco SQLite ja existe: $databasePath" . PHP_EOL);
    exit(1);
}

try {
    $db = db_connect($databasePath, true);
    db_schema($db);
    echo "Banco SQLite vazio criado: $databasePath" . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Inicializacao falhou: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
