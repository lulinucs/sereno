<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/database.php';
require_once dirname(__DIR__) . '/src/devices.php';
require_once dirname(__DIR__) . '/src/device_repository.php';

if (($argv[1] ?? '') === 'setup') {
    $db=db_connect($argv[2],true); db_schema($db);
    $device=['nome'=>'Câmara 1','habilitado'=>true,'temperatura_min'=>1.0,'temperatura_max'=>8.0,'tolerancia_minutos'=>0,'offline_minutos'=>5,'histerese_temperatura'=>0.5,'monitoramentos'=>['temperatura'=>true,'offline'=>true]];
    device_db_insert($db,'camara-01',$device);
    exit;
}

$directory=sys_get_temp_dir().'/camara-api-'.bin2hex(random_bytes(6));
mkdir($directory,0700,true);
$path=$directory.'/test.sqlite';
$testPassword='senha-somente-testes';
$testHash=password_hash($testPassword,PASSWORD_DEFAULT);
$environment=getenv();$environment['SQLITE_FILE']=$path;$environment['DEVICE_CAMARA_01_TOKEN']='test-device-token';$environment['ADMIN_PASSWORD_HASH']=$testHash;
$setup=proc_open([PHP_BINARY,__FILE__,'setup',$path],[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$environment);
if(!is_resource($setup))throw new RuntimeException('Falha ao criar banco de teste.');
stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
if(proc_close($setup)!==0)throw new RuntimeException('Falha ao inicializar banco de teste.');
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
if(!$socket)throw new RuntimeException($error);
$address=stream_socket_get_name($socket,false);fclose($socket);
$port=(int)substr(strrchr($address,':'),1);
$server=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',dirname(__DIR__)],[1=>['file',$directory.'/server.out','w'],2=>['file',$directory.'/server.err','w']],$serverPipes,null,$environment);
if(!is_resource($server))throw new RuntimeException('Falha ao iniciar servidor de teste.');
function api_call(string $url,?array $body=null,string $sessionHeader=''): array {
    $options=['http'=>['ignore_errors'=>true,'timeout'=>5]];
    if($body!==null){$options['http']['method']='POST';$options['http']['content']=json_encode($body,JSON_THROW_ON_ERROR);}
    $options['http']['header']=($body!==null ? 'Content-Type: application/json' : '') . ($sessionHeader ? ($body!==null ? "\r\n" : '').$sessionHeader : '');
    $raw=@file_get_contents($url,false,stream_context_create($options));
    if($raw===false)throw new RuntimeException('Requisicao falhou: '.$url);
    $status=(int)explode(' ',$http_response_header[0])[1];
    return [$status,json_decode($raw,true,512,JSON_THROW_ON_ERROR),$http_response_header];
}
function session_cookie(array $headers): string {
    foreach($headers as $header)if(stripos($header,'Set-Cookie: PHPSESSID=')===0)return trim(explode(';',substr($header,12))[0]);
    return '';
}
try {
    $base='http://127.0.0.1:'.$port.'/api.php?action=';
    $ready=false;
    for($i=0;$i<30;$i++){try{[$status,$before]=api_call($base.'data&device_id=camara-01');$ready=true;break;}catch(Throwable $e){usleep(100000);}}
    if(!$ready||$status!==200)throw new RuntimeException('API nao iniciou.');
    [$status,$pushed]=api_call($base.'push',['device_id'=>'camara-01','temperatura'=>9.2,'umidade'=>80.0,'pressao'=>1010.0],'X-Device-Token: test-device-token');
    if($status!==201||!$pushed['ok']||$pushed['reading']['device_id']!=='camara-01'||$pushed['alert_engine']['events_generated']!==1)throw new RuntimeException('Contrato de ingestao divergente.');
    [$status,$data]=api_call($base.'data&device_id=camara-01&hours=1');
    if($status!==200||count($data['logs'])!==1||$data['latest']['temperatura']!==9.2||$data['state']['status']!=='TEMP_ALTA'||count($data['events'])!==1)throw new RuntimeException('Consulta de dados divergente.');
    [$status,$overview]=api_call($base.'overview');
    if($status!==200||$overview['devices']['camara-01']['state']!=='TEMP_ALTA')throw new RuntimeException('Visao geral divergente.');
    [$status,$events]=api_call($base.'events&limit=50');
    if($status!==200||$events['unread_count']!==1||$events['events'][0]['type']!=='TEMP_HIGH')throw new RuntimeException('Eventos divergentes.');
    $html=file_get_contents('http://127.0.0.1:'.$port.'/');
    if($html===false||!preg_match('/const CSRF_TOKEN = "([^"]+)"/', $html, $matches))throw new RuntimeException('Sessao do dashboard indisponivel.');
    $cookie=session_cookie($http_response_header);
    if($cookie==='')throw new RuntimeException('Cookie de sessao ausente.');
    $csrf=$matches[1];
    $sessionHeader='Cookie: '.$cookie."\r\n".'X-CSRF-Token: '.$csrf;
    [$status,$auth]=api_call($base.'auth_status',null,'Cookie: '.$cookie);
    if($status!==200||$auth['authenticated']!==false)throw new RuntimeException('auth_status publico divergente.');
    $updated=$data['temperature_limits'];
    $device=['nome'=>'Câmara Teste','habilitado'=>true,'temperatura_min'=>$updated['min'],'temperatura_max'=>$updated['max'],'tolerancia_minutos'=>0,'offline_minutos'=>5,'histerese_temperatura'=>0.5,'monitoramentos'=>['temperatura'=>true,'offline'=>true]];
    [$status]=api_call($base.'device_config&device_id=camara-01',$device,$sessionHeader);
    if($status!==401)throw new RuntimeException('Configuracao sem login nao foi bloqueada.');
    [$status]=api_call($base.'test_notification',['device_id'=>'camara-01'],$sessionHeader);
    if($status!==401)throw new RuntimeException('Teste de notificacao sem login nao foi bloqueado.');
    [$status]=api_call($base.'notification_read',['event_id'=>$events['events'][0]['id']],$sessionHeader);
    if($status!==401)throw new RuntimeException('Leitura sem login nao foi bloqueada.');
    [$status]=api_call($base.'notifications_read_all',[],$sessionHeader);
    if($status!==401)throw new RuntimeException('Leitura geral sem login nao foi bloqueada.');
    [$status,$wrong]=api_call($base.'login',['password'=>'incorreta'],$sessionHeader);
    if($status!==401||$wrong['error']!=='Senha incorreta.')throw new RuntimeException('Senha incorreta divergente.');
    [$status,$logged,$loginHeaders]=api_call($base.'login',['password'=>$testPassword],$sessionHeader);
    if($status!==200||$logged['authenticated']!==true)throw new RuntimeException('Login correto falhou.');
    $newCookie=session_cookie($loginHeaders);
    if($newCookie===''||$newCookie===$cookie)throw new RuntimeException('ID de sessao nao foi regenerado.');
    $cookie=$newCookie;
    $sessionHeader='Cookie: '.$cookie."\r\n".'X-CSRF-Token: '.$csrf;
    [$status,$auth]=api_call($base.'auth_status',null,'Cookie: '.$cookie);
    if($status!==200||$auth['authenticated']!==true)throw new RuntimeException('Sessao autenticada nao persistiu.');
    [$status]=api_call($base.'device_config&device_id=camara-01',$device,'Cookie: '.$cookie."\r\n".'X-CSRF-Token: incorreto');
    if($status!==403)throw new RuntimeException('CSRF invalido nao foi rejeitado.');
    [$status,$read]=api_call($base.'notification_read',['event_id'=>$events['events'][0]['id']],$sessionHeader);
    if($status!==200||!$read['ok'])throw new RuntimeException('Marcar notificacao falhou.');
    [$status,$events]=api_call($base.'events&limit=50');
    if($events['unread_count']!==0)throw new RuntimeException('Leitura de notificacao nao persistiu.');
    [$status,$saved]=api_call($base.'device_config&device_id=camara-01',$device,$sessionHeader);
    if($status!==200||$saved['config']['nome']!=='Câmara Teste')throw new RuntimeException('Configuracao HTTP falhou.');
    [$status,$testEvent]=api_call($base.'test_notification',['device_id'=>'camara-01'],$sessionHeader);
    if($status!==201||$testEvent['event']['type']!=='TEST_NOTIFICATION')throw new RuntimeException('Teste autenticado falhou.');
    [$status,$all]=api_call($base.'notifications_read_all',[],$sessionHeader);
    if($status!==200||$all['marked']!==2)throw new RuntimeException('Leitura geral autenticada falhou.');
    [$status,$logout,$logoutHeaders]=api_call($base.'logout',[],$sessionHeader);
    if($status!==200||$logout['authenticated']!==false)throw new RuntimeException('Logout falhou.');
    $cookie=session_cookie($logoutHeaders);
    [$status,$auth]=api_call($base.'auth_status',null,'Cookie: '.$cookie);
    if($status!==200||$auth['authenticated']!==false)throw new RuntimeException('Sessao apos logout ativa.');
    for($i=0;$i<5;$i++)api_call($base.'login',['password'=>'incorreta'],'Cookie: '.$cookie."\r\n".'X-CSRF-Token: '.$csrf);
    [$status,$limited]=api_call($base.'login',['password'=>$testPassword],'Cookie: '.$cookie."\r\n".'X-CSRF-Token: '.$csrf);
    if($status!==429)throw new RuntimeException('Rate limit nao bloqueou.');
    foreach([$wrong,$logged,$auth,$limited,$data,$overview] as $response) {
        $encoded=json_encode($response);
        if(str_contains($encoded,$testPassword)||str_contains($encoded,$testHash))throw new RuntimeException('Senha ou hash exposto na API.');
    }
    echo "OK - API HTTP: leitura pública, ESP, login, CSRF, sessão, logout, rate limit e escritas.\n";
} finally {
    proc_terminate($server);
    proc_close($server);
    if(is_file($path))unlink($path);
    foreach(['server.out','server.err'] as $name)if(is_file($directory.'/'.$name))unlink($directory.'/'.$name);
    rmdir($directory);
}
