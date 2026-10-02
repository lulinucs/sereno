<?php
declare(strict_types=1);

function db_connect(string $path, bool $create = false): PDO
{
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('A extensao pdo_sqlite nao esta habilitada.');
    }
    if (!$create && !is_file($path)) {
        throw new RuntimeException('Banco SQLite nao inicializado. Execute a migracao CLI.');
    }
    if (!is_dir(dirname($path))) {
        throw new RuntimeException('Diretorio de dados nao encontrado.');
    }
    $db = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('PRAGMA foreign_keys = ON');
    // DELETE evita arquivos WAL/SHM persistentes em hospedagem compartilhada.
    $db->exec('PRAGMA journal_mode = DELETE');
    return $db;
}

function db_schema(PDO $db): void
{
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS devices (
  device_id TEXT PRIMARY KEY,
  nome TEXT NOT NULL,
  habilitado INTEGER NOT NULL,
  temperatura_min REAL NOT NULL,
  temperatura_max REAL NOT NULL,
  tolerancia_minutos INTEGER NOT NULL,
  offline_minutos INTEGER NOT NULL,
  histerese_temperatura REAL NOT NULL,
  monitor_temperatura INTEGER NOT NULL,
  monitor_offline INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS readings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  device_id TEXT NOT NULL REFERENCES devices(device_id),
  recorded_at TEXT NOT NULL,
  recorded_epoch INTEGER NOT NULL,
  temperatura REAL NOT NULL,
  umidade REAL NOT NULL,
  pressao REAL NOT NULL,
  source_key TEXT UNIQUE
);
CREATE INDEX IF NOT EXISTS readings_device_time ON readings(device_id, recorded_epoch);
CREATE TABLE IF NOT EXISTS alert_states (
  device_id TEXT PRIMARY KEY REFERENCES devices(device_id),
  status TEXT NOT NULL,
  since TEXT NOT NULL,
  pending_condition TEXT,
  pending_since TEXT,
  last_reading_at TEXT,
  updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS events (
  id TEXT PRIMARY KEY,
  device_id TEXT NOT NULL REFERENCES devices(device_id),
  type TEXT NOT NULL,
  created_at TEXT NOT NULL,
  created_epoch INTEGER NOT NULL,
  previous_state TEXT NOT NULL,
  new_state TEXT NOT NULL,
  message TEXT NOT NULL,
  data_json TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS events_device_time ON events(device_id, created_epoch);
CREATE INDEX IF NOT EXISTS events_time ON events(created_epoch);
CREATE TABLE IF NOT EXISTS notification_reads (
  event_id TEXT PRIMARY KEY REFERENCES events(id) ON DELETE CASCADE,
  read_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS admin_login_attempts (
  client_key TEXT PRIMARY KEY,
  attempts INTEGER NOT NULL,
  window_started INTEGER NOT NULL,
  locked_until INTEGER NOT NULL
);
SQL);
}

function db_transaction(PDO $db, callable $callback)
{
    static $active = [];
    $key = spl_object_id($db);
    if (!empty($active[$key])) return $callback();
    $db->exec('BEGIN IMMEDIATE');
    $active[$key] = true;
    try {
        $result = $callback();
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $exception) {
        $db->exec('ROLLBACK');
        throw $exception;
    } finally {
        unset($active[$key]);
    }
}

function db_execute(PDO $db, string $sql, array $params = []): PDOStatement
{
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function db_timestamp_epoch(string $value): int
{
    try {
        return (new DateTimeImmutable($value))->getTimestamp();
    } catch (Throwable $exception) {
        throw new InvalidArgumentException('Timestamp invalido.', 0, $exception);
    }
}
