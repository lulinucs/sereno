<?php
declare(strict_types=1);

function device_db_load(PDO $db): array
{
    $devices = [];
    foreach ($db->query('SELECT * FROM devices ORDER BY device_id') as $row) {
        $devices[$row['device_id']] = [
            'nome' => $row['nome'], 'habilitado' => (bool) $row['habilitado'],
            'temperatura_min' => (float) $row['temperatura_min'],
            'temperatura_max' => (float) $row['temperatura_max'],
            'tolerancia_minutos' => (int) $row['tolerancia_minutos'],
            'offline_minutos' => (int) $row['offline_minutos'],
            'histerese_temperatura' => (float) $row['histerese_temperatura'],
            'monitoramentos' => ['temperatura' => (bool) $row['monitor_temperatura'], 'offline' => (bool) $row['monitor_offline']],
        ];
    }
    return $devices;
}

function device_db_save_one(PDO $db, string $id, array $input): array
{
    if (!devices_validate_id($id)) throw new InvalidArgumentException('device_id invalido.');
    $value = devices_validate_config($input);
    $statement = db_execute($db, 'UPDATE devices SET nome=?, habilitado=?, temperatura_min=?, temperatura_max=?, tolerancia_minutos=?, offline_minutos=?, histerese_temperatura=?, monitor_temperatura=?, monitor_offline=? WHERE device_id=?', [
        $value['nome'], (int) $value['habilitado'], $value['temperatura_min'], $value['temperatura_max'],
        $value['tolerancia_minutos'], $value['offline_minutos'], $value['histerese_temperatura'],
        (int) $value['monitoramentos']['temperatura'], (int) $value['monitoramentos']['offline'], $id,
    ]);
    if ($statement->rowCount() === 0 && !db_execute($db, 'SELECT 1 FROM devices WHERE device_id=?', [$id])->fetchColumn()) {
        throw new OutOfBoundsException('Dispositivo nao cadastrado.');
    }
    return $value;
}

function device_db_insert(PDO $db, string $id, array $device): void
{
    if (!devices_validate_id($id)) throw new InvalidArgumentException('device_id invalido.');
    $v = devices_validate_config($device);
    db_execute($db, 'INSERT OR IGNORE INTO devices VALUES (?,?,?,?,?,?,?,?,?,?)', [
        $id, $v['nome'], (int) $v['habilitado'], $v['temperatura_min'], $v['temperatura_max'],
        $v['tolerancia_minutos'], $v['offline_minutos'], $v['histerese_temperatura'],
        (int) $v['monitoramentos']['temperatura'], (int) $v['monitoramentos']['offline'],
    ]);
}
