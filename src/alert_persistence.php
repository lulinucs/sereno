<?php
declare(strict_types=1);

function alert_paths(array $config): PDO
{
    return $config['db'];
}

function alert_db_states(PDO $db): array
{
    $states = [];
    foreach ($db->query('SELECT * FROM alert_states') as $row) {
        $states[$row['device_id']] = [
            'status' => $row['status'], 'since' => $row['since'],
            'pending_condition' => $row['pending_condition'], 'pending_since' => $row['pending_since'],
            'last_reading_at' => $row['last_reading_at'], 'updated_at' => $row['updated_at'],
        ];
    }
    return $states;
}

function alert_db_save_state(PDO $db, string $id, array $state): void
{
    db_execute($db, 'INSERT INTO alert_states (device_id,status,since,pending_condition,pending_since,last_reading_at,updated_at) VALUES (?,?,?,?,?,?,?) ON CONFLICT(device_id) DO UPDATE SET status=excluded.status,since=excluded.since,pending_condition=excluded.pending_condition,pending_since=excluded.pending_since,last_reading_at=excluded.last_reading_at,updated_at=excluded.updated_at', [
        $id, $state['status'], $state['since'], $state['pending_condition'] ?? null,
        $state['pending_since'] ?? null, $state['last_reading_at'] ?? null, $state['updated_at'],
    ]);
}

function alert_db_insert_event(PDO $db, array $event): void
{
    $data = json_encode($event['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    db_execute($db, 'INSERT INTO events (id,device_id,type,created_at,created_epoch,previous_state,new_state,message,data_json) VALUES (?,?,?,?,?,?,?,?,?)', [
        $event['id'], $event['device_id'], $event['type'], $event['created_at'],
        db_timestamp_epoch((string) $event['created_at']), $event['previous_state'],
        $event['new_state'], $event['message'], $data,
    ]);
}

function alert_with_lock(PDO $db, callable $callback, bool $persist = true)
{
    $run = static function () use ($db, $callback, $persist) {
        $states = alert_db_states($db);
        $events = [];
        $result = $callback($states, $events);
        if ($persist) {
            foreach ($states as $id => $state) alert_db_save_state($db, $id, $state);
            foreach ($events as $event) alert_db_insert_event($db, $event);
        }
        return $result;
    };
    return $persist ? db_transaction($db, $run) : $run();
}

function alert_get_state(array $config, string $deviceId): ?array
{
    $row = db_execute($config['db'], 'SELECT * FROM alert_states WHERE device_id=?', [$deviceId])->fetch();
    if (!$row) return null;
    return [
        'status' => $row['status'], 'since' => $row['since'],
        'pending_condition' => $row['pending_condition'], 'pending_since' => $row['pending_since'],
        'last_reading_at' => $row['last_reading_at'], 'updated_at' => $row['updated_at'],
    ];
}
