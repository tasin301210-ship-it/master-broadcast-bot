<?php
declare(strict_types=1);

function route_request():void{
 try{
  install_schema();
  if($_SERVER['REQUEST_METHOD']==='GET'){header('Content-Type: application/json');echo json_encode(['ok'=>true,'service'=>'render-master-broadcast-bot']);return;}
  $u=json_input();if(!$u){http_response_code(400);echo'Bad request';return;}
  if(!tg_secret_ok(envv('TELEGRAM_WEBHOOK_SECRET'))){http_response_code(403);echo'Forbidden';return;}
  handle_master_update($u);echo'OK';
 }catch(Throwable $e){http_response_code(500);header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
}
function route_child_webhook():void{
 try{
  install_schema();$id=(int)($_GET['bot']??0);if($id<1){http_response_code(400);return;}
  $s=db()->prepare('SELECT * FROM bots WHERE id=?');$s->execute([$id]);$b=$s->fetch();if(!$b){http_response_code(404);return;}
  if(!tg_secret_ok($b['webhook_secret'])){http_response_code(403);return;}
  $u=json_input();if($u)handle_child_update($b,$u);echo'OK';
 }catch(Throwable $e){http_response_code(500);echo'Error';}
}
function master_send(int $id,string $text,array $extra=[]):void{
 $p=['chat_id'=>(string)$id,'text'=>$text,'parse_mode'=>'HTML'];foreach($extra as $k=>$v)$p[$k]=$v;tg_request(envv('MASTER_BOT_TOKEN'),'sendMessage',$p);
}
function state(int $id):?array{$s=db()->prepare('SELECT * FROM admin_state WHERE admin_id=?');$s->execute([$id]);return $s->fetch()?:null;}
function set_state(int $id,string $st,?int $bot=null):void{$s=db()->prepare('INSERT INTO admin_state(admin_id,state,bot_id) VALUES(?,?,?) ON CONFLICT(admin_id) DO UPDATE SET state=EXCLUDED.state,bot_id=EXCLUDED.bot_id,updated_at=NOW()');$s->execute([$id,$st,$bot]);}
function clear_state(int $id):void{db()->prepare('DELETE FROM admin_state WHERE admin_id=?')->execute([$id]);}

function handle_master_update(array $u):void{
 $cb=$u['callback_query']??null;$m=$u['message']??null;
 if($cb){$id=(int)($cb['from']['id']??0);if(!is_admin($id))return;handle_callback($cb,$id);return;}
 if(!$m)return;$id=(int)($m['from']['id']??0);if(!is_admin($id))return;
 $text=(string)($m['text']??'');$st=state($id);
 if($text==='/start'||$text==='🔙 Back'){clear_state($id);master_send($id,"👑 <b>Master Broadcast Manager</b>",['reply_markup'=>json_encode(master_keyboard())]);return;}
 if($text==='➕ Add Bot'){set_state($id,'token');master_send($id,"🤖 <b>Add Child Bot</b>

BotFather token পাঠান।",['reply_markup'=>json_encode(back_keyboard())]);return;}
 if($text==='🤖 Manage Bots'){show_bots($id);return;}
 if($text==='📊 Stats'){show_stats($id);return;}
 if($text==='📢 Broadcast'){show_picker($id);return;}
 if($st&&$st['state']==='token'&&$text!==''){connect_bot($id,trim($text));return;}
 if($st&&$st['state']==='broadcast'){broadcast($id,$st,$m);return;}
}
function handle_callback(array $cb,int $admin):void{
 tg_answer_callback(envv('MASTER_BOT_TOKEN'),(string)$cb['id']);$d=(string)($cb['data']??'');
 if(str_starts_with($d,'bcast:')){$bid=(int)substr($d,6);$s=db()->prepare('SELECT * FROM bots WHERE id=?');$s->execute([$bid]);$b=$s->fetch();if(!$b){master_send($admin,'❌ Bot not found');return;}set_state($admin,'broadcast',$bid);master_send($admin,"📢 <b>@".h($b['username'])."</b>

এখন message/media পাঠান।");}
}
function connect_bot(int $admin,string $token):void{
 $me=tg_request($token,'getMe');if(!($me['ok']??false)){master_send($admin,'❌ Invalid token: '.h((string)($me['description']??'')));return;}
 $r=$me['result'];$chk=db()->prepare('SELECT id FROM bots WHERE bot_id=?');$chk->execute([(int)$r['id']]);if($chk->fetch()){clear_state($admin);master_send($admin,'⚠️ Bot already connected.');return;}
 $sec=bin2hex(random_bytes(20));$s=db()->prepare('INSERT INTO bots(bot_id,username,first_name,token_enc,webhook_secret) VALUES(?,?,?,?,?)');$s->execute([(int)$r['id'],$r['username']??'',$r['first_name']??'',encrypt_token($token),$sec]);$id=(int)db()->lastInsertId();
 $hook=tg_set_webhook($token,public_url().'/child-webhook.php?bot='.$id,$sec);clear_state($admin);
 master_send($admin,($hook['ok']??false)?"✅ <b>@".h($r['username']??'')."</b> connected.
Webhook: ✅":"⚠️ Bot saved, webhook error: ".h((string)($hook['description']??'')));
}
function show_bots(int $id):void{
 $b=db()->query('SELECT username,bot_id FROM bots ORDER BY id DESC')->fetchAll();if(!$b){master_send($id,'📭 No connected bots.');return;}
 $o="🤖 <b>Connected Bots</b>

";foreach($b as $i=>$x)$o.=($i+1).". @".h($x['username'])." — <code>".$x['bot_id']."</code>
";master_send($id,$o);
}
function show_stats(int $id):void{
 $b=db()->query('SELECT id,username FROM bots ORDER BY id DESC')->fetchAll();if(!$b){master_send($id,'📊 No bots.');return;}
 $o="📊 <b>Stats</b>

";$total=0;foreach($b as $x){$s=db()->prepare('SELECT COUNT(*) FROM users WHERE bot_id=?');$s->execute([$x['id']]);$n=(int)$s->fetchColumn();$total+=$n;$o.="🤖 @".h($x['username'])." — <b>$n</b> users
";}$o.="
👥 Total: <b>$total</b>";master_send($id,$o);
}
function show_picker(int $id):void{
 $b=db()->query('SELECT id,username,bot_id FROM bots ORDER BY id DESC')->fetchAll();if(!$b){master_send($id,'❌ আগে Add Bot করুন.');return;}
 master_send($id,'📢 <b>Select Bot</b>', ['reply_markup'=>json_encode(inline_bots($b))]);
}
function broadcast(int $admin,array $st,array $m):void{
 $s=db()->prepare('SELECT * FROM bots WHERE id=?');$s->execute([(int)$st['bot_id']]);$b=$s->fetch();if(!$b){clear_state($admin);return;}
 $c=content_from_message($m);if($c['type']==='unsupported'){master_send($admin,'❌ এই message type supported নয়.');return;}
 $users=db()->prepare('SELECT id,telegram_id FROM users WHERE bot_id=? AND is_blocked=FALSE');$users->execute([$b['id']]);$rows=$users->fetchAll();$ok=0;$fail=0;$token=decrypt_token($b['token_enc']);
 foreach($rows as $u){$r=tg_send($token,$u['telegram_id'],$c['method'],$c['params']);if($r['ok']??false)$ok++;else{$fail++;$d=strtolower((string)($r['description']??''));if(str_contains($d,'blocked')||str_contains($d,'chat not found')||str_contains($d,'deactivated'))db()->prepare('UPDATE users SET is_blocked=TRUE WHERE id=?')->execute([$u['id']]);}usleep(50000);}
 $x=db()->prepare('INSERT INTO broadcasts(bot_id,admin_id,content_type,total,success,failed) VALUES(?,?,?,?,?,?)');$x->execute([$b['id'],$admin,$c['type'],count($rows),$ok,$fail]);clear_state($admin);
 master_send($admin,"📢 <b>BROADCAST COMPLETED</b>

🤖 @".h($b['username'])."
👥 Total: <b>".count($rows)."</b>
✅ Success: <b>$ok</b>
❌ Failed: <b>$fail</b>",['reply_markup'=>json_encode(master_keyboard())]);
}
function handle_child_update(array $b,array $u):void{
 $m=$u['message']??$u['edited_message']??null;if(!$m)return;$chat=$m['chat']??[];if(($chat['type']??'')!=='private')return;$f=$m['from']??[];
 $s=db()->prepare('INSERT INTO users(bot_id,telegram_id,username,first_name,is_blocked,updated_at) VALUES(?,?,?,?,FALSE,NOW()) ON CONFLICT(bot_id,telegram_id) DO UPDATE SET username=EXCLUDED.username,first_name=EXCLUDED.first_name,is_blocked=FALSE,updated_at=NOW()');
 $s->execute([$b['id'],(int)($f['id']??$chat['id']),$f['username']??null,$f['first_name']??null]);
}
