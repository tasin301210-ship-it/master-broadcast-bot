<?php
declare(strict_types=1);
function tg_request(string $token,string $method,array $params=[]):array{
 $ch=curl_init('https://api.telegram.org/bot'.$token.'/'.$method);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$params,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>60]);
 $raw=curl_exec($ch);$err=curl_error($ch);curl_close($ch);
 if($raw===false)return['ok'=>false,'description'=>$err];
 $d=json_decode($raw,true);return is_array($d)?$d:['ok'=>false,'description'=>'Invalid Telegram response'];
}
function tg_send(string $token,int|string $chat,string $method,array $p=[]):array{$p['chat_id']=(string)$chat;return tg_request($token,$method,$p);}
function tg_set_webhook(string $token,string $url,string $secret):array{return tg_request($token,'setWebhook',['url'=>$url,'secret_token'=>$secret]);}
function tg_answer_callback(string $token,string $id):void{tg_request($token,'answerCallbackQuery',['callback_query_id'=>$id]);}
function content_from_message(array $m):array{
 if(isset($m['text']))return['type'=>'text','method'=>'sendMessage','params'=>['text'=>$m['text']]];
 foreach(['photo','video','audio','document','voice','animation'] as $t)if(isset($m[$t])){
  $v=$m[$t];if($t==='photo')$v=$v[count($v)-1];$p=[$t=>$v['file_id']];
  if(isset($m['caption']))$p['caption']=$m['caption'];if(isset($m['caption_entities']))$p['caption_entities']=json_encode($m['caption_entities']);
  if(isset($m['reply_markup']))$p['reply_markup']=json_encode($m['reply_markup']);
  return['type'=>$t,'method'=>'send'.ucfirst($t),'params'=>$p];
 }
 return['type'=>'unsupported','method'=>'','params'=>[]];
}
