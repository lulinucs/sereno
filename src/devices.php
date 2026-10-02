<?php
declare(strict_types=1);

function devices_validate_id(string $deviceId): bool
{
    return (bool) preg_match('/^[a-z0-9][a-z0-9-]{1,63}$/', $deviceId);
}

function devices_validate_config(array $device): array
{
    $required = ['nome', 'habilitado', 'temperatura_min', 'temperatura_max', 'tolerancia_minutos', 'offline_minutos', 'histerese_temperatura', 'monitoramentos'];
    foreach ($required as $field) {
        if (!array_key_exists($field, $device)) {
            throw new InvalidArgumentException('Campo ausente: ' . $field . '.');
        }
    }
    if (!is_string($device['nome']) || trim($device['nome']) === '' || strlen(trim($device['nome'])) > 160) {
        throw new InvalidArgumentException('Nome deve ter entre 1 e 80 caracteres.');
    }
    if (!is_bool($device['habilitado'])) {
        throw new InvalidArgumentException('habilitado deve ser booleano.');
    }
    foreach (['temperatura_min', 'temperatura_max', 'histerese_temperatura'] as $field) {
        if ((!is_int($device[$field]) && !is_float($device[$field])) || !is_finite((float) $device[$field])) {
            throw new InvalidArgumentException($field . ' deve ser numerico e finito.');
        }
    }
    if ((float) $device['temperatura_min'] >= (float) $device['temperatura_max']) {
        throw new InvalidArgumentException('Temperatura minima deve ser menor que a maxima.');
    }
    if ((float) $device['temperatura_min'] < -50 || (float) $device['temperatura_max'] > 80) {
        throw new InvalidArgumentException('Temperaturas devem ficar entre -50 e 80 °C.');
    }
    if ((float) $device['histerese_temperatura'] < 0 || (float) $device['histerese_temperatura'] > 20) {
        throw new InvalidArgumentException('Histerese deve ficar entre 0 e 20 °C.');
    }
    foreach (['tolerancia_minutos', 'offline_minutos'] as $field) {
        if (!is_int($device[$field])) {
            throw new InvalidArgumentException($field . ' deve ser um inteiro.');
        }
    }
    if ($device['tolerancia_minutos'] < 0 || $device['tolerancia_minutos'] > 10080) {
        throw new InvalidArgumentException('Tolerancia deve ficar entre 0 e 10080 minutos.');
    }
    if ($device['offline_minutos'] <= 0 || $device['offline_minutos'] > 10080) {
        throw new InvalidArgumentException('Tempo offline deve ficar entre 1 e 10080 minutos.');
    }
    if (!is_array($device['monitoramentos'])) {
        throw new InvalidArgumentException('monitoramentos deve ser um objeto.');
    }
    foreach (['temperatura', 'offline'] as $field) {
        if (!array_key_exists($field, $device['monitoramentos']) || !is_bool($device['monitoramentos'][$field])) {
            throw new InvalidArgumentException('Monitoramento ' . $field . ' deve ser booleano.');
        }
    }
    return [
        'nome' => trim($device['nome']),
        'habilitado' => $device['habilitado'],
        'temperatura_min' => (float) $device['temperatura_min'],
        'temperatura_max' => (float) $device['temperatura_max'],
        'tolerancia_minutos' => $device['tolerancia_minutos'],
        'offline_minutos' => $device['offline_minutos'],
        'histerese_temperatura' => (float) $device['histerese_temperatura'],
        'monitoramentos' => ['temperatura' => $device['monitoramentos']['temperatura'], 'offline' => $device['monitoramentos']['offline']],
    ];
}

function devices_public(array $devices): array
{
    return array_map(static function (array $device): array {
        unset($device['token']);
        return $device;
    }, $devices);
}
