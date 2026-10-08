<?php
declare(strict_types=1);
function db():PDO{
 static $pdo=null;if($pdo instanceof PDO)return $pdo;
 $url=envv('DATABASE_URL');if($url==='')throw new RuntimeException('DATABASE_URL is missing.');
 $p=parse_url($url);if(!$p||empty($p['host']))throw new RuntimeException('DATABASE_URL is invalid.');
 $dsn='pgsql:host='.$p['host'].';port='.($p['port']??5432).';dbname='.ltrim($p['path']??'','/');
 $pdo=new PDO($dsn,urldecode($p['user']??''),urldecode($p['pass']??''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 return $pdo;
}
function install_schema():void{
 $p=db();
 $qs=[
 "CREATE TABLE IF NOT EXISTS bots(id BIGSERIAL PRIMARY KEY,bot_id BIGINT UNIQUE NOT NULL,username VARCHAR(255) NOT NULL,first_name VARCHAR(255),token_enc TEXT NOT NULL,webhook_secret VARCHAR(255) NOT NULL,created_at TIMESTAMPTZ DEFAULT NOW())",
 "CREATE TABLE IF NOT EXISTS users(id BIGSERIAL PRIMARY KEY,bot_id BIGINT REFERENCES bots(id) ON DELETE CASCADE,telegram_id BIGINT NOT NULL,username VARCHAR(255),first_name VARCHAR(255),is_blocked BOOLEAN DEFAULT FALSE,created_at TIMESTAMPTZ DEFAULT NOW(),updated_at TIMESTAMPTZ DEFAULT NOW(),UNIQUE(bot_id,telegram_id))",
 "CREATE TABLE IF NOT EXISTS broadcasts(id BIGSERIAL PRIMARY KEY,bot_id BIGINT REFERENCES bots(id) ON DELETE CASCADE,admin_id BIGINT NOT NULL,content_type VARCHAR(50) NOT NULL,total INT DEFAULT 0,success INT DEFAULT 0,failed INT DEFAULT 0,created_at TIMESTAMPTZ DEFAULT NOW())",
 "CREATE TABLE IF NOT EXISTS admin_state(admin_id BIGINT PRIMARY KEY,state VARCHAR(50) NOT NULL,bot_id BIGINT,updated_at TIMESTAMPTZ DEFAULT NOW())"
 ];
 foreach($qs as $q)$p->exec($q);
}
function encrypt_token(string $t):string{$s=envv('TOKEN_ENCRYPTION_KEY');if($s==='')throw new RuntimeException('TOKEN_ENCRYPTION_KEY is missing.');$k=hash('sha256',$s,true);$iv=random_bytes(16);$e=openssl_encrypt($t,'AES-256-CBC',$k,OPENSSL_RAW_DATA,$iv);if($e===false)throw new RuntimeException('Encryption failed.');return base64_encode($iv.$e);}
function decrypt_token(string $d):string{$s=envv('TOKEN_ENCRYPTION_KEY');$r=base64_decode($d,true);if($s===''||$r===false||strlen($r)<17)throw new RuntimeException('Stored token invalid.');$k=hash('sha256',$s,true);$e=openssl_decrypt(substr($r,16),'AES-256-CBC',$k,OPENSSL_RAW_DATA,substr($r,0,16));if($e===false)throw new RuntimeException('Decryption failed.');return $e;}
