<?php
declare(strict_types=1);
function envv(string $k,string $d=''):string{$v=getenv($k);return($v===false||$v==='')?$d:(string)$v;}
function h(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function json_input():array{$r=file_get_contents('php://input');if(!$r)return[];$d=json_decode($r,true);return is_array($d)?$d:[];}
function is_admin(int $id):bool{$a=array_filter(array_map('trim',explode(',',envv('ADMIN_IDS'))));return in_array((string)$id,$a,true);}
function public_url():string{return rtrim(envv('PUBLIC_URL'),'/');}
function master_keyboard():array{return['keyboard'=>[[['text'=>'➕ Add Bot'],['text'=>'🤖 Manage Bots']],[['text'=>'📢 Broadcast'],['text'=>'📊 Stats']]],'resize_keyboard'=>true];}
function back_keyboard():array{return['keyboard'=>[[['text'=>'🔙 Back']]],'resize_keyboard'=>true];}
function inline_bots(array $bots):array{$r=[];foreach($bots as $b)$r[]=[['text'=>'@'.($b['username']?:$b['bot_id']),'callback_data'=>'bcast:'.$b['id']]];return['inline_keyboard'=>$r];}
function tg_secret_ok(string $expected):bool{if($expected==='')return true;$h=$_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']??'';$q=(string)($_GET['secret']??'');return($h!==''&&hash_equals($expected,$h))||($q!==''&&hash_equals($expected,$q));}
