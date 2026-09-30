<?php
// Uses a new, disposable database. Existing application rows are never changed.
$root=dirname(__DIR__);
$env=parse_ini_file($root.'/xyzsniper/config/.env');
$mode=strtoupper($env['APP_MODE']??'local');
$dbName='sniper_test_'.bin2hex(random_bytes(5));
foreach(['HOST','PORT','USER','PASS'] as $key) define('DB_'.$key,$env['DB_'.$key.'_'.$mode]);
define('DB_NAME',$dbName);
define('burl','http://localhost:8000'); define('APP_CANONICAL_URL',burl);
$pdo=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4");
require $root.'/xyzsniper/core/Database.php';require $root.'/xyzsniper/core/BaseModel.php';require $root.'/xyzsniper/core/CaptureInput.php';require $root.'/xyzsniper/app/models/CaptureModel.php';
function check($condition,$message){if(!$condition)throw new RuntimeException($message);echo "PASS $message\n";}
function callMcp($name,$args,$account=1){
  global $root;
  $code='<?php ';
  foreach(['DB_HOST','DB_PORT','DB_USER','DB_PASS','DB_NAME','burl','APP_CANONICAL_URL'] as $constant)$code.='define('.var_export($constant,true).','.var_export(constant($constant),true).');';
  foreach(['/xyzsniper/core/Controller.php','/xyzsniper/core/Database.php','/xyzsniper/app/controllers/back/Mcp.php'] as $file)$code.='require '.var_export($root.$file,true).';';
  $code.='$m=new Mcp();$r=new ReflectionMethod($m,"handleToolCall");$r->invoke($m,1,'.var_export($name,true).','.var_export($args,true).','.$account.');';
  $process=proc_open(['php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);fwrite($pipes[0],$code);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);proc_close($process);
  $result=json_decode($out,true);if(!$result)throw new RuntimeException('Invalid MCP output: '.$err.' '.$out);return $result;
}
try{
  $pdo->exec("USE `$dbName`");
  $schema=file_get_contents($root.'/xyzsniper/database/schema.sql');
  $schema=preg_replace('/^(CREATE DATABASE.*|USE xyzsniper_db;)\R/m','',$schema);$pdo->exec($schema);
  $pdo->exec("INSERT INTO accounts(id,email,name) VALUES(1,'fixture@example.test','Fixture'),(2,'other@example.test','Other'); INSERT INTO projects(id,account_id,title,domain) VALUES(1,1,'Fixture','example.test'),(2,2,'Other','other.test')");
  $projects=[['id'=>1,'domain'=>'example.test']];
  $base=['event_id'=>'evt_first','session_id'=>'ses_fixture','pageUrl'=>'https://example.test/dashboard','url'=>'https://api.example.test/sales?period=today','method'=>'GET','source_type'=>'fetch','occurred_at'=>1780000000123,'requestHeaders'=>['Authorization'=>'private','x-filter'=>'today'],'responseBody'=>['sales'=>0,'password'=>'private']];
  $model=new CaptureModel();$first=CaptureInput::normalize($base,$projects);$saved=$model->saveBatch(1,[$first]);
  $second=CaptureInput::normalize(array_replace($base,['event_id'=>'evt_second','responseBody'=>['sales'=>1234]]),$projects);$model->saveBatch(1,[$second]);
  $model->saveBatch(1,[$first]);
  check((int)$pdo->query('SELECT COUNT(*) FROM payloads')->fetchColumn()===2,'repeated request keeps both events, retry adds none');
  check((int)$pdo->query('SELECT SUM(hit_count) FROM payloads')->fetchColumn()===2,'retry does not inflate hit count');
  $row=$pdo->query("SELECT * FROM payloads WHERE event_id='evt_first'")->fetch(PDO::FETCH_ASSOC);
  check(json_decode($row['response_body'],true)['sales']===0,'zero is retained');
  check(str_contains($row['request_header'],'[REDACTED]')&&!str_contains($row['request_header'],'private'),'per-event headers are redacted');
  check(str_ends_with($row['occurred_at'],'.123'),'event timestamp keeps milliseconds');
  $foreign=false;try{CaptureInput::normalize($base,[['id'=>2,'domain'=>'other.test']]);}catch(InvalidArgumentException $e){$foreign=true;}check($foreign,'foreign domain rejected');
  $snap=array_replace($base,['event_id'=>'evt_snapshot','source_type'=>'snapshot','responseBody'=>['title'=>'Fixture dashboard','url'=>'https://example.test/dashboard','filters'=>[['label'=>'Periode','value'=>'Hari ini']],'metrics'=>[['raw_text'=>'Rp1.234,50','context'=>'Penjualan','value_decimal'=>null]],'text'=>'contoh','coverage'=>'visible_dom_only']]);$model->saveBatch(1,[CaptureInput::normalize($snap,$projects)]);
  $metric=callMcp('get_dashboard_metrics',['event_id'=>'evt_snapshot'])['result']['structuredContent'];check($metric['items'][0]['raw_text']==='Rp1.234,50'&&$metric['filters'][0]['value']==='Hari ini','MCP metric includes original value and period');
  check(isset(callMcp('get_event',['event_id'=>'evt_first'],2)['error']),'MCP denies cross-account event');
  $page=callMcp('get_session_events',['session_id'=>'ses_fixture','limit'=>1])['result']['structuredContent'];check($page['pagination']['has_more']&&$page['pagination']['next_offset']===1,'MCP pagination announces remaining rows');
  $search=callMcp('search_traffic',['keyword'=>'period=today','project_id'=>1])['result']['structuredContent'];check(count($search['items'])===3,'search includes URL query parameters');
  $unicode=str_repeat('angka 日本 ',1000);$long=array_replace($base,['event_id'=>'evt_long','responseBody'=>$unicode]);$model->saveBatch(1,[CaptureInput::normalize($long,$projects)]);
  $cursor=0;$rebuilt='';do{$part=callMcp('read_body',['event_id'=>'evt_long','part'=>'response','cursor'=>$cursor,'max_bytes'=>1024])['result']['structuredContent'];$rebuilt.=$part['text'];$cursor=$part['next_cursor'];}while($cursor!==null);check($rebuilt===$unicode,'MCP body chunks reconstruct UTF-8 exactly');
  $preview=callMcp('get_event',['event_id'=>'evt_long'])['result']['structuredContent'];check($preview['response_body_preview_only']===true,'large body uses explicit preview');
  $pdo->exec("UPDATE capture_sessions SET status='ended' WHERE session_id='ses_fixture'");$model->saveBatch(1,[$first]);check($pdo->query("SELECT status FROM capture_sessions WHERE session_id='ses_fixture'")->fetchColumn()==='ended','late retry cannot reopen ended session');
  echo "Integration checks complete. Disposable database removed below.\n";
}finally{$pdo->exec("DROP DATABASE `$dbName`");}
