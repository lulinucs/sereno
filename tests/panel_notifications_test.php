<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/database.php';
require_once dirname(__DIR__) . '/src/devices.php';
require_once dirname(__DIR__) . '/src/device_repository.php';
require_once dirname(__DIR__) . '/src/storage.php';
require_once dirname(__DIR__) . '/src/alert_engine.php';
require_once dirname(__DIR__) . '/src/alert_persistence.php';
require_once dirname(__DIR__) . '/src/notifications.php';

$db = db_connect(':memory:', true); db_schema($db);
$base=['nome'=>'Câmara 1','habilitado'=>true,'temperatura_min'=>1.0,'temperatura_max'=>8.0,'tolerancia_minutos'=>5,'offline_minutos'=>5,'histerese_temperatura'=>0.5,'monitoramentos'=>['temperatura'=>true,'offline'=>true]];
$other=$base; $other['nome']='Câmara 2'; $other['temperatura_max']=7.0;
device_db_insert($db,'camara-01',$base); device_db_insert($db,'camara-02',$other);
$config=['db'=>$db,'devices'=>device_db_load($db)];
$count=0;
function panel_check(bool $value,string $message):void { global $count; $count++; if(!$value) throw new RuntimeException($message); }
try {
    panel_check(device_db_load($db)['camara-01']['temperatura_max']===8.0,'carregar');
    $changed=$base; $changed['tolerancia_minutos']=7;
    panel_check(device_db_save_one($db,'camara-01',$changed)['tolerancia_minutos']===7 && device_db_load($db)['camara-01']['tolerancia_minutos']===7,'salvar');
    $invalid=$changed; $invalid['temperatura_min']=9.0;
    try {device_db_save_one($db,'camara-01',$invalid); panel_check(false,'minima aceita');} catch(InvalidArgumentException $e) {panel_check(true,'minima rejeitada');}
    $invalid=$changed; $invalid['offline_minutos']=0;
    try {device_db_save_one($db,'camara-01',$invalid); panel_check(false,'offline aceito');} catch(InvalidArgumentException $e) {panel_check(true,'offline rejeitado');}
    panel_check(device_db_load($db)['camara-02']['tolerancia_minutos']===5,'independencia');
    $events=[];
    alert_add_event($events,'camara-01','TEMP_HIGH','2026-01-01T12:00:00+00:00','NORMAL','TEMP_ALTA','Alta',['temperature'=>9,'limit'=>8]);
    alert_add_event($events,'camara-02','OFFLINE','2026-01-01T12:01:00+00:00','NORMAL','OFFLINE','Offline');
    foreach($events as $event) alert_db_insert_event($db,$event);
    $listed=notification_list($config);
    panel_check(count($listed['events'])===2,'listar');
    panel_check($listed['unread_count']===2,'nao lidos');
    panel_check(notification_mark_read($config,$events[0]['id']),'marcar um');
    panel_check(notification_list($config)['unread_count']===1,'contador');
    panel_check(notification_mark_all_read($config)===2,'marcar todos');
    panel_check(notification_list($config)['unread_count']===0,'todos lidos');
    $stateCount=(int)$db->query('SELECT COUNT(*) FROM alert_states')->fetchColumn();
    $readingCount=(int)$db->query('SELECT COUNT(*) FROM readings')->fetchColumn();
    $test=notification_create_test_event($config,'camara-01');
    panel_check($test['type']==='TEST_NOTIFICATION' && $test['data']['test']===true,'teste');
    panel_check((int)$db->query('SELECT COUNT(*) FROM alert_states')->fetchColumn()===$stateCount,'teste nao altera estado');
    panel_check((int)$db->query('SELECT COUNT(*) FROM readings')->fetchColumn()===$readingCount,'teste nao altera leitura');
    $config['devices']=device_db_load($db);
    $reading=['device_id'=>'camara-01','timestamp'=>'2026-01-01T13:00:00+00:00','temperatura'=>8.5,'umidade'=>80,'pressao'=>1010];
    alert_process_reading($config,'camara-01',$reading);
    $reading['timestamp']='2026-01-01T13:06:00+00:00';
    panel_check(alert_process_reading($config,'camara-01',$reading)===[],'tolerancia editada');
    $reading['timestamp']='2026-01-01T13:07:00+00:00';
    panel_check(alert_process_reading($config,'camara-01',$reading)[0]['type']==='TEMP_HIGH','config usada no motor');
    echo 'OK - '.$count." asserções.\n";
} finally { unset($db,$config); }
