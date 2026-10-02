<?php
declare(strict_types=1);

function notification_decode_event(array $row): array
{
    return [
        'id' => $row['id'], 'device_id' => $row['device_id'], 'type' => $row['type'],
        'created_at' => $row['created_at'], 'previous_state' => $row['previous_state'],
        'new_state' => $row['new_state'], 'message' => $row['message'],
        'data' => json_decode($row['data_json'], true, 512, JSON_THROW_ON_ERROR),
    ];
}

function notification_events(array $config, ?string $deviceId = null, ?int $sinceEpoch = null): array
{
    $conditions = [];
    $params = [];
    if ($deviceId !== null) { $conditions[] = 'device_id=?'; $params[] = $deviceId; }
    if ($sinceEpoch !== null) { $conditions[] = 'created_epoch>=?'; $params[] = $sinceEpoch; }
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    $rows = db_execute($config['db'], 'SELECT * FROM events' . $where . ' ORDER BY created_epoch, rowid', $params)->fetchAll();
    return array_map('notification_decode_event', $rows);
}

function notification_list(array $config, ?string $deviceId = null, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $where = $deviceId === null ? '' : ' WHERE e.device_id=?';
    $params = $deviceId === null ? [] : [$deviceId];
    $unread = (int) db_execute($config['db'], 'SELECT COUNT(*) FROM events e LEFT JOIN notification_reads n ON n.event_id=e.id' . ($where ? $where . ' AND' : ' WHERE') . ' n.event_id IS NULL', $params)->fetchColumn();
    $rows = db_execute($config['db'], 'SELECT e.*, n.event_id AS read_id FROM events e LEFT JOIN notification_reads n ON n.event_id=e.id' . $where . ' ORDER BY e.rowid DESC LIMIT ' . $limit, $params)->fetchAll();
    $events = [];
    foreach ($rows as $row) {
        $event = notification_decode_event($row);
        $event['read'] = $row['read_id'] !== null;
        $event['device_name'] = $config['devices'][$event['device_id']]['nome'] ?? $event['device_id'];
        $events[] = $event;
    }
    return ['events' => $events, 'unread_count' => $unread];
}

function notification_mark_read(array $config, string $eventId): bool
{
    if (!preg_match('/^[a-zA-Z0-9._-]{8,128}$/', $eventId)) throw new InvalidArgumentException('ID de evento invalido.');
    if (!db_execute($config['db'], 'SELECT 1 FROM events WHERE id=?', [$eventId])->fetchColumn()) return false;
    db_execute($config['db'], 'INSERT INTO notification_reads(event_id,read_at) VALUES (?,?) ON CONFLICT(event_id) DO NOTHING', [$eventId, date(DATE_ATOM)]);
    return true;
}

function notification_mark_all_read(array $config, ?string $deviceId = null): int
{
    return db_transaction($config['db'], static function () use ($config, $deviceId): int {
        $where = $deviceId === null ? '' : ' WHERE device_id=?';
        $params = $deviceId === null ? [] : [$deviceId];
        $count = (int) db_execute($config['db'], 'SELECT COUNT(*) FROM events' . $where, $params)->fetchColumn();
        $sql = 'INSERT OR IGNORE INTO notification_reads(event_id,read_at) SELECT id, ? FROM events' . ($deviceId === null ? '' : ' WHERE device_id=?');
        db_execute($config['db'], $sql, [date(DATE_ATOM), ...$params]);
        return $count;
    });
}

function notification_create_test_event(array $config, string $deviceId): array
{
    if (!isset($config['devices'][$deviceId])) throw new OutOfBoundsException('Dispositivo nao cadastrado.');
    return db_transaction($config['db'], static function () use ($config, $deviceId): array {
        $events = [];
        $event = alert_add_event($events, $deviceId, 'TEST_NOTIFICATION', date(DATE_ATOM), 'UNCHANGED', 'UNCHANGED', 'Sistema de notificações funcionando.', ['test' => true]);
        alert_db_insert_event($config['db'], $event);
        return $event;
    });
}
