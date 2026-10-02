<?php
declare(strict_types=1);

function storage_add(PDO $db, array $reading): int
{
    db_execute($db, 'INSERT INTO readings (device_id, recorded_at, recorded_epoch, temperatura, umidade, pressao) VALUES (?,?,?,?,?,?)', [
        $reading['device_id'], $reading['timestamp'], db_timestamp_epoch((string) $reading['timestamp']),
        $reading['temperatura'], $reading['umidade'], $reading['pressao'],
    ]);
    return (int) $db->query('SELECT COUNT(*) FROM readings')->fetchColumn();
}

function storage_for_device(PDO $db, string $deviceId, int $sinceEpoch): array
{
    $rows = db_execute($db, 'SELECT device_id, recorded_at AS timestamp, temperatura, umidade, pressao FROM readings WHERE device_id=? AND recorded_epoch>=? ORDER BY recorded_epoch, id', [$deviceId, $sinceEpoch])->fetchAll();
    foreach ($rows as &$row) {
        $row['temperatura'] = (float) $row['temperatura'];
        $row['umidade'] = (float) $row['umidade'];
        $row['pressao'] = (float) $row['pressao'];
    }
    return $rows;
}

function storage_latest(PDO $db, string $deviceId): ?array
{
    $row = db_execute($db, 'SELECT device_id, recorded_at AS timestamp, temperatura, umidade, pressao FROM readings WHERE device_id=? ORDER BY recorded_epoch DESC, id DESC LIMIT 1', [$deviceId])->fetch();
    return $row ?: null;
}

function storage_latest_all(PDO $db): array
{
    $rows = $db->query('SELECT d.device_id, r.recorded_at AS timestamp, r.temperatura, r.umidade, r.pressao FROM devices d LEFT JOIN readings r ON r.id = (SELECT x.id FROM readings x WHERE x.device_id=d.device_id ORDER BY x.recorded_epoch DESC, x.id DESC LIMIT 1)')->fetchAll();
    $byDevice = [];
    foreach ($rows as $row) if ($row['timestamp'] !== null) $byDevice[$row['device_id']] = $row;
    return $byDevice;
}
