<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/database.php';
require_once dirname(__DIR__) . '/src/devices.php';
require_once dirname(__DIR__) . '/src/device_repository.php';
require_once dirname(__DIR__) . '/src/storage.php';

if (($argv[1] ?? '') === 'setup') {
    $db=db_connect($argv[2],true); db_schema($db);
    $device=['nome'=>'Câmara 1','habilitado'=>true,'temperatura_min'=>1.0,'temperatura_max'=>8.0,'tolerancia_minutos'=>5,'offline_minutos'=>5,'histerese_temperatura'=>0.5,'monitoramentos'=>['temperatura'=>true,'offline'=>true]];
    device_db_insert($db,'camara-01',$device);
    storage_add($db,['device_id'=>'camara-01','timestamp'=>(new DateTimeImmutable('-10 minutes'))->format(DATE_ATOM),'temperatura'=>4,'umidade'=>80,'pressao'=>1010]);
    exit(0);
}
if (($argv[1] ?? '') === 'verify') {
    $db=db_connect($argv[2]);
    $state=$db->query("SELECT status FROM alert_states WHERE device_id='camara-01'")->fetchColumn();
    $events=(int)$db->query("SELECT COUNT(*) FROM events WHERE type='OFFLINE'")->fetchColumn();
    if($state!=='OFFLINE'||$events!==1) {fwrite(STDERR,"Monitor divergente: $state/$events\n");exit(1);}
    exit(0);
}

$directory=sys_get_temp_dir().'/camara-monitor-'.bin2hex(random_bytes(6));
mkdir($directory,0700,true);
$path=$directory.'/test.sqlite';
function run_php(array $command, array $environment): array {
    $process=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$environment);
    if(!is_resource($process))throw new RuntimeException('Falha ao abrir processo PHP.');
    $out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($process),$out,$error];
}
try {
    $env=getenv(); $env['SQLITE_FILE']=$path;
    [$code,, $error]=run_php([PHP_BINARY,__FILE__,'setup',$path],$env);
    if($code!==0)throw new RuntimeException('Setup: '.$error);
    $monitor=dirname(__DIR__).'/monitor.php';
    [$code,$out,$error]=run_php([PHP_BINARY,$monitor],$env);
    if($code!==0||!str_contains($out,'1 evento(s)'))throw new RuntimeException('Primeiro monitor: '.$out.$error);
    [$code,$out,$error]=run_php([PHP_BINARY,$monitor],$env);
    if($code!==0||!str_contains($out,'0 evento(s)'))throw new RuntimeException('Segundo monitor: '.$out.$error);
    [$code,, $error]=run_php([PHP_BINARY,__FILE__,'verify',$path],$env);
    if($code!==0)throw new RuntimeException('Verificacao: '.$error);
    echo "OK - monitor offline, transação e ausência de evento duplicado.\n";
} finally {
    if(is_file($path))unlink($path);
    rmdir($directory);
}
