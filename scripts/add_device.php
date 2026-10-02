<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$config = require dirname(__DIR__) . '/src/config.php';
if ($argc !== 3 || !devices_validate_id($argv[1])) {
    fwrite(STDERR, "Uso: php scripts/add_device.php camara-03 config.json\n");
    exit(1);
}
try {
    if (isset($config['devices'][$argv[1]])) throw new RuntimeException('Dispositivo ja cadastrado.');
    $raw = file_get_contents($argv[2]);
    if ($raw === false) throw new RuntimeException('Nao foi possivel ler o arquivo de configuracao.');
    $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException('Configuracao deve ser um objeto JSON.');
    device_db_insert($config['db'], $argv[1], $value);
    echo "Dispositivo cadastrado: " . $argv[1] . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Cadastro falhou: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
