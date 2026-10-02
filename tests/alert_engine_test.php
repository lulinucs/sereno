<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/database.php';
require_once dirname(__DIR__) . '/src/devices.php';
require_once dirname(__DIR__) . '/src/device_repository.php';
require_once dirname(__DIR__) . '/src/alert_engine.php';
require_once dirname(__DIR__) . '/src/alert_persistence.php';
require_once dirname(__DIR__) . '/src/notifications.php';

$directory = sys_get_temp_dir() . '/camara-alert-' . bin2hex(random_bytes(6));
mkdir($directory, 0700, true);
$db = db_connect($directory . '/test.sqlite', true);
db_schema($db);
$base = ['nome'=>'Câmara 1','habilitado'=>true,'temperatura_min'=>1.0,'temperatura_max'=>8.0,'tolerancia_minutos'=>5,'offline_minutos'=>5,'histerese_temperatura'=>0.5,'monitoramentos'=>['temperatura'=>true,'offline'=>true]];
$other = $base; $other['nome']='Câmara 2'; $other['temperatura_max']=7.0; $other['tolerancia_minutos']=2;
device_db_insert($db, 'camara-01', $base);
device_db_insert($db, 'camara-02', $other);
$config = ['db'=>$db,'devices'=>device_db_load($db)];
$count = 0;
function check(bool $condition, string $message): void { global $count; $count++; if (!$condition) throw new RuntimeException($message); }
function reading(string $id, string $time, float $temp): array { return ['device_id'=>$id,'timestamp'=>$time,'temperatura'=>$temp,'umidade'=>80.0,'pressao'=>1010.0]; }
function types(array $config): array { return array_column(notification_events($config), 'type'); }

try {
    check(alert_process_reading($config,'camara-01',reading('camara-01','2026-01-01T12:00:00+00:00',4))===[], 'normal');
    check(alert_process_reading($config,'camara-01',reading('camara-01','2026-01-01T12:01:00+00:00',8.5))===[], 'alta pendente');
    check(alert_process_reading($config,'camara-01',reading('camara-01','2026-01-01T12:05:59+00:00',9))===[], 'tolerancia');
    check(alert_process_reading($config,'camara-01',reading('camara-01','2026-01-01T12:06:00+00:00',9.2))[0]['type']==='TEMP_HIGH', 'alta confirmada');
    check(alert_process_reading($config,'camara-01',reading('camara-01','2026-01-01T12:10:00+00:00',9.1))===[], 'sem spam');
    check(alert_process_reading($config,'camara-01',reading('camara-01','2026-01-01T12:11:00+00:00',7.8))===[], 'histerese');
    check(alert_process_reading($config,'camara-01',reading('camara-01','2026-01-01T12:12:00+00:00',7.5))[0]['type']==='NORMALIZED', 'normalizacao');
    check(alert_process_reading($config,'camara-01',reading('camara-01','2026-01-01T12:13:00+00:00',4))===[], 'normal persistente');
    check(alert_process_reading($config,'camara-01',reading('camara-01','2026-01-01T12:14:00+00:00',0.8))===[], 'baixa pendente');
    check(alert_process_reading($config,'camara-01',reading('camara-01','2026-01-01T12:19:00+00:00',0.5))[0]['type']==='TEMP_LOW', 'baixa confirmada');
    check(alert_process_reading($config,'camara-01',reading('camara-01','2026-01-01T12:20:00+00:00',1.5))[0]['type']==='NORMALIZED', 'baixa normalizada');
    $latest=reading('camara-01','2026-01-01T12:20:00+00:00',1.5);
    $offline=alert_with_lock($db, static function(array &$states,array &$events) use($config,$latest):array { return alert_apply_offline($states,$events,'camara-01',$config['devices']['camara-01'],$latest,new DateTimeImmutable('2026-01-01T12:25:00+00:00')); });
    check(count($offline)===1 && $offline[0]['type']==='OFFLINE', 'offline');
    check(alert_with_lock($db, static function(array &$states,array &$events) use($config,$latest):array { return alert_apply_offline($states,$events,'camara-01',$config['devices']['camara-01'],$latest,new DateTimeImmutable('2026-01-01T12:30:00+00:00')); })===[], 'offline sem spam');
    check(alert_process_reading($config,'camara-01',reading('camara-01','2026-01-01T12:31:00+00:00',4))[0]['type']==='ONLINE', 'online');
    check(alert_process_reading($config,'camara-01',reading('camara-01','2026-01-01T12:32:00+00:00',4))===[], 'online sem spam');
    check(alert_process_reading($config,'camara-02',reading('camara-02','2026-01-01T13:00:00+00:00',7.1))===[], 'camara 2 pendente');
    check(alert_process_reading($config,'camara-02',reading('camara-02','2026-01-01T13:02:00+00:00',7.2))[0]['type']==='TEMP_HIGH', 'camara 2 tolerancia');
    check(alert_get_state($config,'camara-01')['status']==='NORMAL' && alert_get_state($config,'camara-02')['status']==='TEMP_ALTA', 'isolamento');
    check(count(array_filter(types($config), static fn($type)=>$type==='OFFLINE'))===1, 'um offline');
    check(count(array_filter(types($config), static fn($type)=>$type==='ONLINE'))===1, 'um online');
    echo 'OK - '.$count." asserções.\n";
} finally {
    unset($db, $config);
    unlink($directory.'/test.sqlite');
    rmdir($directory);
}
