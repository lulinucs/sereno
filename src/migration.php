<?php
declare(strict_types=1);

function migration_json(string $path, array $default): array
{
    if (!is_file($path)) return $default;
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Nao foi possivel ler ' . basename($path) . '.');
    try { $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException(basename($path) . ' contem JSON invalido.', 0, $e); }
    if (!is_array($value)) throw new RuntimeException(basename($path) . ' deve conter array ou objeto.');
    return $value;
}

function migration_sources(string $directory, string $defaultDevice): array
{
    $devices = migration_json($directory . '/devices.json', []);
    if (!$devices) throw new RuntimeException('devices.json deve conter pelo menos um dispositivo.');
    foreach ($devices as $id => $device) {
        if (!is_string($id) || !devices_validate_id($id) || !is_array($device)) throw new RuntimeException('Dispositivo invalido no JSON.');
        $devices[$id] = devices_validate_config($device);
    }
    $readings = migration_json($directory . '/leituras.json', []);
    foreach ($readings as $index => &$reading) {
        if (!is_array($reading)) throw new RuntimeException('Leitura invalida no indice ' . $index . '.');
        $reading['device_id'] = $reading['device_id'] ?? $defaultDevice;
        if (!isset($devices[$reading['device_id']]) || !isset($reading['timestamp'])) throw new RuntimeException('Leitura com dispositivo ou timestamp invalido no indice ' . $index . '.');
        db_timestamp_epoch((string) $reading['timestamp']);
        foreach (['temperatura', 'umidade', 'pressao'] as $field) {
            if (!isset($reading[$field]) || !is_numeric($reading[$field]) || !is_finite((float) $reading[$field])) throw new RuntimeException('Leitura com ' . $field . ' invalido no indice ' . $index . '.');
        }
    }
    unset($reading);
    $events = migration_json($directory . '/events.json', []);
    foreach ($events as $index => $event) {
        if (!is_array($event) || !isset($event['id'], $event['device_id'], $event['type'], $event['created_at'], $event['previous_state'], $event['new_state'], $event['message']) || !isset($devices[$event['device_id']]) || !is_array($event['data'] ?? [])) throw new RuntimeException('Evento invalido no indice ' . $index . '.');
        db_timestamp_epoch((string) $event['created_at']);
    }
    $states = migration_json($directory . '/state.json', []);
    foreach ($states as $id => $state) {
        if (!isset($devices[$id]) || !is_array($state) || !isset($state['status'], $state['since'], $state['updated_at'])) throw new RuntimeException('Estado invalido para ' . $id . '.');
        db_timestamp_epoch((string) $state['since']);
        db_timestamp_epoch((string) $state['updated_at']);
    }
    $notification = migration_json($directory . '/notification_state.json', ['read' => []]);
    if (!isset($notification['read']) || !is_array($notification['read'])) throw new RuntimeException('notification_state.json invalido.');
    return compact('devices', 'readings', 'events', 'states') + ['read' => $notification['read']];
}

function migration_import(PDO $db, array $source): array
{
    return db_transaction($db, static function () use ($db, $source): array {
        foreach ($source['devices'] as $id => $device) device_db_insert($db, $id, $device);
        foreach ($source['readings'] as $index => $reading) {
            $key = hash('sha256', $index . ':' . json_encode($reading, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            db_execute($db, 'INSERT OR IGNORE INTO readings (device_id,recorded_at,recorded_epoch,temperatura,umidade,pressao,source_key) VALUES (?,?,?,?,?,?,?)', [
                $reading['device_id'], $reading['timestamp'], db_timestamp_epoch((string) $reading['timestamp']),
                $reading['temperatura'], $reading['umidade'], $reading['pressao'], $key,
            ]);
        }
        foreach ($source['states'] as $id => $state) {
            if (!db_execute($db, 'SELECT 1 FROM alert_states WHERE device_id=?', [$id])->fetchColumn()) alert_db_save_state($db, $id, $state);
        }
        foreach ($source['events'] as $event) {
            if (!db_execute($db, 'SELECT 1 FROM events WHERE id=?', [$event['id']])->fetchColumn()) alert_db_insert_event($db, $event);
        }
        foreach ($source['read'] as $id => $at) {
            if (!is_string($id) || !is_string($at)) throw new RuntimeException('Estado de leitura invalido.');
            if (db_execute($db, 'SELECT 1 FROM events WHERE id=?', [$id])->fetchColumn()) {
                db_execute($db, 'INSERT OR IGNORE INTO notification_reads(event_id,read_at) VALUES (?,?)', [$id, $at]);
            }
        }
        return migration_verify($db, $source);
    });
}

function migration_verify(PDO $db, array $source): array
{
    $result = [];
    $savedDevices = device_db_load($db);
    $result['device_configs_matching_source'] = 0;
    foreach ($source['devices'] as $id => $device) {
        $saved = $savedDevices[$id] ?? null;
        if ($saved === null) throw new RuntimeException('Dispositivo ausente apos migracao: ' . $id);
        if ($saved === $device) $result['device_configs_matching_source']++;
    }
    $result['devices'] = count($source['devices']);
    $result['readings'] = count($source['readings']);
    $result['readings_by_device'] = [];
    foreach ($source['readings'] as $reading) $result['readings_by_device'][$reading['device_id']] = ($result['readings_by_device'][$reading['device_id']] ?? 0) + 1;
    foreach ($result['readings_by_device'] as $id => $count) {
        $actual = (int) db_execute($db, 'SELECT COUNT(*) FROM readings WHERE device_id=? AND source_key IS NOT NULL', [$id])->fetchColumn();
        if ($actual < $count) throw new RuntimeException('Leituras divergentes para ' . $id);
    }
    foreach ($source['events'] as $event) {
        $row = db_execute($db, 'SELECT type,created_at FROM events WHERE id=?', [$event['id']])->fetch();
        if (!$row || $row['type'] !== $event['type'] || $row['created_at'] !== $event['created_at']) throw new RuntimeException('Evento divergente: ' . $event['id']);
    }
    $result['events'] = count($source['events']);
    $result['states'] = count($source['states']);
    $result['states_matching_source'] = 0;
    foreach ($source['states'] as $id => $state) {
        $saved = db_execute($db, 'SELECT * FROM alert_states WHERE device_id=?', [$id])->fetch();
        if (!$saved) throw new RuntimeException('Estado ausente: ' . $id);
        $normalized = ['status'=>$saved['status'],'since'=>$saved['since'],'pending_condition'=>$saved['pending_condition'],'pending_since'=>$saved['pending_since'],'last_reading_at'=>$saved['last_reading_at'],'updated_at'=>$saved['updated_at']];
        if ($normalized == $state) $result['states_matching_source']++;
    }
    $result['read'] = 0;
    foreach ($source['read'] as $id => $_) {
        if (db_execute($db, 'SELECT 1 FROM notification_reads WHERE event_id=?', [$id])->fetchColumn()) $result['read']++;
    }
    return $result;
}
