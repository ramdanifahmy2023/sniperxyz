<?php
$root=dirname(__DIR__);$env=parse_ini_file($root.'/xyzsniper/config/.env');$mode=strtoupper($env['APP_MODE']??'local');
$pdo=new PDO('mysql:host='.$env['DB_HOST_'.$mode].';port='.$env['DB_PORT_'.$mode],$env['DB_USER_'.$mode],$env['DB_PASS_'.$mode],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(($argv[1]??'')==='cleanup'){
  $name=$argv[2]??'';if(!preg_match('/^sniper_e2e_[a-f0-9]{12}$/',$name))exit(2);
  $pdo->exec("DROP DATABASE IF EXISTS `$name`");exit;
}
$name='sniper_e2e_'.bin2hex(random_bytes(6));$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4");$pdo->exec("USE `$name`");
$schema=preg_replace('/^(CREATE DATABASE.*|USE xyzsniper_db;)\R/m','',file_get_contents($root.'/xyzsniper/database/schema.sql'));$pdo->exec($schema);
$password=password_hash('Sniper-fixture-only-2026',PASSWORD_DEFAULT);$token=bin2hex(random_bytes(24));
$q=$pdo->prepare('INSERT INTO accounts(id,email,name,password) VALUES(1,?,?,?)');$q->execute(['fixture@example.test','Fixture test',$password]);
$q=$pdo->prepare('INSERT INTO projects(id,account_id,title,domain,is_active) VALUES(1,1,?,?,1)');$q->execute(['Business Insights fixture',$argv[2]]);
$q=$pdo->prepare('INSERT INTO extension_tokens(account_id,token) VALUES(1,?)');$q->execute([$token]);
$env['APP_MODE']='local';$env['APP_URL_LOCAL']=$argv[3];
foreach(['HOST','PORT','USER','PASS'] as $key)$env['DB_'.$key.'_LOCAL']=$env['DB_'.$key.'_'.$mode];$env['DB_NAME_LOCAL']=$name;
$file=$argv[1];$text='';foreach($env as $key=>$value)$text.=$key.'="'.addcslashes((string)$value,'"\\').'"'.PHP_EOL;
file_put_contents($file,$text);chmod($file,0600);echo json_encode(['database'=>$name,'token'=>$token]);
