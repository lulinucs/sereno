<?php

declare(strict_types=1);

const ALERT_NORMAL = 'NORMAL';
const ALERT_TEMP_HIGH = 'TEMP_ALTA';
const ALERT_TEMP_LOW = 'TEMP_BAIXA';
const ALERT_OFFLINE = 'OFFLINE';

function alert_timestamp(string $value): DateTimeImmutable
{
    try {
        return new DateTimeImmutable($value);
    } catch (Throwable $exception) {
        throw new InvalidArgumentException('Timestamp invalido no Alert Engine.', 0, $exception);
    }
}

function alert_initial_state(string $timestamp): array
{
    return [
        'status' => ALERT_NORMAL,
        'since' => $timestamp,
        'pending_condition' => null,
        'pending_since' => null,
        'last_reading_at' => $timestamp,
        'updated_at' => $timestamp,
    ];
}

function alert_temperature_condition(float $temperature, array $device): string
{
    if (!(bool) ($device['habilitado'] ?? true) || !(bool) ($device['monitoramentos']['temperatura'] ?? true)) {
        return ALERT_NORMAL;
    }
    if ($temperature >= (float) $device['temperatura_max']) {
        return ALERT_TEMP_HIGH;
    }
    if ($temperature <= (float) $device['temperatura_min']) {
        return ALERT_TEMP_LOW;
    }
    return ALERT_NORMAL;
}

function alert_is_recovered(float $temperature, string $status, array $device): bool
{
    if (!(bool) ($device['habilitado'] ?? true) || !(bool) ($device['monitoramentos']['temperatura'] ?? true)) {
        return true;
    }

    $hysteresis = max(0.0, (float) ($device['histerese_temperatura'] ?? 0.5));
    if ($status === ALERT_TEMP_HIGH) {
        return $temperature <= (float) $device['temperatura_max'] - $hysteresis;
    }
    if ($status === ALERT_TEMP_LOW) {
        return $temperature >= (float) $device['temperatura_min'] + $hysteresis;
    }
    return false;
}

function alert_event_id(): string
{
    try {
        return bin2hex(random_bytes(12));
    } catch (Throwable $exception) {
        return str_replace('.', '', uniqid('', true));
    }
}

function alert_add_event(
    array &$events,
    string $deviceId,
    string $type,
    string $createdAt,
    string $previousState,
    string $newState,
    string $message,
    array $data = []
): array {
    $event = [
        'id' => alert_event_id(),
        'device_id' => $deviceId,
        'type' => $type,
        'created_at' => $createdAt,
        'previous_state' => $previousState,
        'new_state' => $newState,
        'message' => $message,
        'data' => $data,
    ];
    $events[] = $event;
    return $event;
}

function alert_apply_reading(
    array &$states,
    array &$events,
    string $deviceId,
    array $device,
    array $reading
): array {
    $timestamp = (string) $reading['timestamp'];
    $at = alert_timestamp($timestamp);
    $temperature = (float) $reading['temperatura'];
    $state = $states[$deviceId] ?? alert_initial_state($timestamp);
    $generated = [];
    $recoveredFromOffline = false;

    if (!empty($state['last_reading_at']) && $at < alert_timestamp((string) $state['last_reading_at'])) {
        return [];
    }

    if (($state['status'] ?? ALERT_NORMAL) === ALERT_OFFLINE) {
        $recoveredFromOffline = true;
        $generated[] = alert_add_event(
            $events,
            $deviceId,
            'ONLINE',
            $timestamp,
            ALERT_OFFLINE,
            ALERT_NORMAL,
            $device['nome'] . ' voltou a enviar leituras.',
            ['temperature' => $temperature]
        );
        $state = alert_initial_state($timestamp);
    }

    $status = (string) ($state['status'] ?? ALERT_NORMAL);
    $condition = alert_temperature_condition($temperature, $device);

    if ($status === ALERT_TEMP_HIGH || $status === ALERT_TEMP_LOW) {
        if (alert_is_recovered($temperature, $status, $device)) {
            $generated[] = alert_add_event(
                $events,
                $deviceId,
                'NORMALIZED',
                $timestamp,
                $status,
                ALERT_NORMAL,
                $device['nome'] . ' voltou à faixa normal de temperatura.',
                ['temperature' => $temperature]
            );
            $state = alert_initial_state($timestamp);
            $condition = alert_temperature_condition($temperature, $device);
        }
    }

    if (($state['status'] ?? ALERT_NORMAL) === ALERT_NORMAL) {
        if ($condition === ALERT_NORMAL) {
            $state['pending_condition'] = null;
            $state['pending_since'] = null;
        } else {
            if (($state['pending_condition'] ?? null) !== $condition) {
                $state['pending_condition'] = $condition;
                $state['pending_since'] = $timestamp;
            }

            $pendingAt = alert_timestamp((string) $state['pending_since']);
            $elapsedSeconds = max(0, $at->getTimestamp() - $pendingAt->getTimestamp());
            $toleranceSeconds = max(0, (int) $device['tolerancia_minutos']) * 60;

            if (!$recoveredFromOffline && $elapsedSeconds >= $toleranceSeconds) {
                $type = $condition === ALERT_TEMP_HIGH ? 'TEMP_HIGH' : 'TEMP_LOW';
                $limit = $condition === ALERT_TEMP_HIGH
                    ? (float) $device['temperatura_max']
                    : (float) $device['temperatura_min'];
                $message = $condition === ALERT_TEMP_HIGH
                    ? $device['nome'] . ' está acima da temperatura máxima.'
                    : $device['nome'] . ' está abaixo da temperatura mínima.';

                $generated[] = alert_add_event(
                    $events,
                    $deviceId,
                    $type,
                    $timestamp,
                    ALERT_NORMAL,
                    $condition,
                    $message,
                    ['temperature' => $temperature, 'limit' => $limit]
                );
                $state['status'] = $condition;
                $state['since'] = $timestamp;
                $state['pending_condition'] = null;
                $state['pending_since'] = null;
            }
        }
    }

    $state['last_reading_at'] = $timestamp;
    $state['updated_at'] = $timestamp;
    $states[$deviceId] = $state;
    return $generated;
}

function alert_process_reading(array $config, string $deviceId, array $reading): array
{
    $device = $config['devices'][$deviceId];
    return alert_with_lock(
        alert_paths($config),
        static function (array &$states, array &$events) use ($deviceId, $device, $reading): array {
            return alert_apply_reading($states, $events, $deviceId, $device, $reading);
        }
    );
}

function alert_apply_offline(
    array &$states,
    array &$events,
    string $deviceId,
    array $device,
    ?array $latest,
    DateTimeImmutable $now
): array {
    if (!(bool) ($device['habilitado'] ?? true) || !(bool) ($device['monitoramentos']['offline'] ?? true)) {
        return [];
    }

    $nowText = $now->format(DATE_ATOM);
    $latestAt = $latest === null ? null : alert_timestamp((string) $latest['timestamp']);
    $state = $states[$deviceId] ?? alert_initial_state($latestAt ? $latestAt->format(DATE_ATOM) : $nowText);
    $offlineSeconds = max(1, (int) $device['offline_minutos']) * 60;
    $ageSeconds = $latestAt === null ? null : $now->getTimestamp() - $latestAt->getTimestamp();

    if ($latestAt !== null && $ageSeconds < $offlineSeconds) {
        $states[$deviceId] = $state;
        return [];
    }
    if (($state['status'] ?? ALERT_NORMAL) === ALERT_OFFLINE) {
        return [];
    }

    $previous = (string) ($state['status'] ?? ALERT_NORMAL);
    $state['status'] = ALERT_OFFLINE;
    $state['since'] = $nowText;
    $state['pending_condition'] = null;
    $state['pending_since'] = null;
    $state['updated_at'] = $nowText;
    if ($latestAt !== null) {
        $state['last_reading_at'] = $latestAt->format(DATE_ATOM);
    }
    $states[$deviceId] = $state;

    return [alert_add_event(
        $events,
        $deviceId,
        'OFFLINE',
        $nowText,
        $previous,
        ALERT_OFFLINE,
        $device['nome'] . ' parou de enviar leituras.',
        ['last_reading_at' => $latestAt ? $latestAt->format(DATE_ATOM) : null, 'age_seconds' => $ageSeconds]
    )];
}

