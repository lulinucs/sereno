<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/database.php';
require_once dirname(__DIR__) . '/src/devices.php';
require_once dirname(__DIR__) . '/src/device_repository.php';
require_once dirname(__DIR__) . '/src/storage.php';
require_once dirname(__DIR__) . '/src/alert_engine.php';
require_once dirname(__DIR__) . '/src/alert_persistence.php';
require_once dirname(__DIR__) . '/src/notifications.php';
require_once dirname(__DIR__) . '/src/migration.php';

$dir = sys_get_temp_dir() . '/camara-migration-' . bin2hex(random_bytes(6));
mkdir($dir, 0700, true);
$base = ['nome'=>'Câmara 1','habilitado'=>true,'temperatura_min'=>1.0,'temperatura_max'=>8.0,'tolerancia_minutos'=>5,'offline_minutos'=>5,'histerese_temperatura'=>0.5,'monitoramentos'=>['temperatura'=>true,'offline'=>true]];
$second=$base; $second['nome']='Câmara 2';
$stamp='2026-01-01T12:00:00+00:00';
$reading=['timestamp'=>$stamp,'temperatura'=>4.2,'umidade'=>80.0,'pressao'=>1010.0];
$event=['id'=>'migration-event-001','device_id'=>'camara-01','type'=>'TEMP_HIGH','created_at'=>$stamp,'previous_state'=>'NORMAL','new_state'=>'TEMP_ALTA','message'=>'Alerta','data'=>['temperature'=>9.0]];
$state=['status'=>'TEMP_ALTA','since'=>$stamp,'pending_condition'=>null,'pending_since'=>null,'last_reading_at'=>$stamp,'updated_at'=>$stamp];
$files=['devices.json'=>['camara-01'=>$base,'camara-02'=>$second], 'leituras.json'=>[$reading,$reading+['device_id'=>'camara-02']], 'events.json'=>[$event], 'state.json'=>['camara-01'=>$state], 'notification_state.json'=>['read'=>[$event['id']=>$stamp]]];
foreach($files as $name=>$value) file_put_contents($dir.'/'.$name,json_encode($value,JSON_THROW_ON_ERROR));
$db=db_connect(':memory:',true); db_schema($db);
$count=0;
function migration_check(bool $ok,string $message):void {global $count;$count++;if(!$ok)throw new RuntimeException($message);}
try {
    migration_check((int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('readings','devices','alert_states','events','notification_reads')")->fetchColumn()===5,'schema');
    migration_check((int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name='readings_device_time'")->fetchColumn()===1,'indice leituras');
    $source=migration_sources($dir,'camara-01');
    $result=migration_import($db,$source);
    migration_check($result['devices']===2 && $result['readings']===2 && $result['events']===1 && $result['states']===1 && $result['read']===1,'comparacao');
    migration_check($result['readings_by_device']===['camara-01'=>1,'camara-02'=>1],'leituras por dispositivo');
    migration_import($db,$source);
    migration_check((int)$db->query('SELECT COUNT(*) FROM readings')->fetchColumn()===2 && (int)$db->query('SELECT COUNT(*) FROM events')->fetchColumn()===1,'idempotencia');
    migration_check(notification_list(['db'=>$db,'devices'=>device_db_load($db)])['unread_count']===0,'lido migrado');
    migration_check(alert_get_state(['db'=>$db],'camara-01')['status']==='TEMP_ALTA','estado migrado');
    migration_check(storage_latest($db,'camara-02')['temperatura']==4.2,'ultima leitura');
    migration_check(count(storage_for_device($db,'camara-01',db_timestamp_epoch($stamp)-1))===1,'consulta periodo');
    $new=['device_id'=>'camara-01','timestamp'=>'2026-01-01T12:01:00+00:00','temperatura'=>4.1,'umidade'=>81.0,'pressao'=>1011.0];
    storage_add($db,$new);
    migration_check((int)$db->query('SELECT COUNT(*) FROM readings')->fetchColumn()===3,'ingestao');
    try { db_transaction($db,static function()use($db,$new):void {storage_add($db,$new);throw new RuntimeException('rollback');}); migration_check(false,'rollback ausente'); }
    catch(RuntimeException $e) { migration_check((int)$db->query('SELECT COUNT(*) FROM readings')->fetchColumn()===3,'rollback'); }
    file_put_contents($dir.'/events.json','{invalid');
    try {migration_sources($dir,'camara-01');migration_check(false,'JSON invalido aceito');}
    catch(RuntimeException $e) {migration_check(true,'JSON invalido rejeitado');}
    echo 'OK - '.$count." asserções.\n";
} finally {
    unset($db);
    foreach(array_keys($files) as $name) if(is_file($dir.'/'.$name)) unlink($dir.'/'.$name);
    rmdir($dir);
}
