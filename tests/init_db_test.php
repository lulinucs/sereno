<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/database.php';

$directory = sys_get_temp_dir() . '/sereno-init-' . bin2hex(random_bytes(6));
mkdir($directory, 0700, true);
$path = $directory . '/test.sqlite';

try {
    $db = db_connect($path, true);
    db_schema($db);
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $expected = ['admin_login_attempts', 'alert_states', 'devices', 'events', 'notification_reads', 'readings'];
    if ($tables !== $expected) throw new RuntimeException('Schema criado diverge do schema esperado.');
    echo "OK - banco SQLite vazio e schema atual criados em arquivo temporario.\n";
} finally {
    unset($db);
    if (is_file($path)) unlink($path);
    rmdir($directory);
}
