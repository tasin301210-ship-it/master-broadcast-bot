<?php
declare(strict_types=1);

function route_request(): void {
    install_schema();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!secret_ok()) { http_response_code(403); exit('Forbidden'); }
        echo 'Master Broadcast Bot is running.';
        return;
    }

    if (!secret_ok()) { http_response_code(403); exit('Forbidden'); }

    $update = json_decode(file_get_contents('php://input'), true);
    if (!is_array($update)) { http_response_code(400); exit; }

    if (isset($update['callback_query'])) {
        handle_callback($update['callback_query']);
    } elseif (isset($update['message'])) {
        handle_master_message($update['message']);
    }
    echo 'OK';
}

function handle_master_message(array $m): void {
    $from = $m['from'] ?? [];
    $uid = (int)($from['id'] ?? 0);
    if (!is_admin($uid)) return;

    $chat = (int)($m['chat']['id'] ?? 0);
    $text = trim((string)($m['text'] ?? ''));

    if ($text === '/start' || $text === '🏠 Menu') {
        admin_menu($chat);
        return;
    }

    if ($text === '➕ Add Bot') {
        send_admin('sendMessage', [
            'chat_id'=>$chat,
            'text'=>"🔑 <b>Send Bot Token</b>\n\nSend the BotFather token of the bot you want to connect.\n\n⚠️ Keep your token private.",
            'parse_mode'=>'HTML'
        ]);
        set_state($uid, 'await_token');
        return;
    }

    if ($text === '🤖 Manage Bots') {
        show_bots($chat);
        return;
    }

    if ($text === '📢 Broadcast') {
        show_bots_for_broadcast($chat);
        return;
    }

    $state = get_state($uid);
    if ($state !== null && str_starts_with($state, 'broadcast_')) {
        $botId = (int)substr($state, strlen('broadcast_'));
        process_master_broadcast_message($m, $uid, $chat, $botId);
        clear_state($uid);
        return;
    }

    if ($state === 'await_token' && $text !== '') {
        connect_bot($chat, $uid, $text);
        return;
    }

    send_admin('sendMessage', ['chat_id'=>$chat,'text'=>'Use the menu buttons.']);
}

function admin_menu(int $chat): void {
    send_admin('sendMessage', [
        'chat_id'=>$chat,
        'text'=>"👑 <b>MASTER BROADCAST PANEL</b>\n\nChoose an option:",
        'parse_mode'=>'HTML',
        'reply_markup'=>json_encode(keyboard([
            [button('➕ Add Bot','noop'), button('🤖 Manage Bots','bots')],
            [button('📢 Broadcast','broadcast')],
        ]), JSON_UNESCAPED_UNICODE)
    ]);
}

function handle_callback(array $q): void {
    $uid=(int)($q['from']['id']??0);
    $chat=(int)($q['message']['chat']['id']??0);
    $data=(string)($q['data']??'');
    if (!is_admin($uid)) return;
    telegram_call(envv('MASTER_BOT_TOKEN',''),'answerCallbackQuery',[
        'callback_query_id'=>$q['id']
    ]);

    if ($data==='bots') { show_bots($chat); return; }
    if ($data==='broadcast') { show_bots_for_broadcast($chat); return; }
    if ($data==='menu') { admin_menu($chat); return; }
    if (str_starts_with($data,'bc:')) {
        $botId=(int)substr($data,3);
        start_broadcast($chat,$uid,$botId);
        return;
    }
    if (str_starts_with($data,'del:')) {
        $botId=(int)substr($data,4);
        delete_bot($chat,$botId);
        return;
    }
}

function connect_bot(int $chat, int $uid, string $token): void {
    $token=trim($token);
    $me=telegram_call($token,'getMe');
    if (!($me['ok']??false)) {
        send_admin('sendMessage',['chat_id'=>$chat,'text'=>'❌ Invalid Bot Token.']);
        return;
    }

    $r=$me['result'];
    $hash=hash('sha256',$token);
    $pdo=db();
    $s=$pdo->prepare('SELECT id FROM bots WHERE token_hash=?');
    $s->execute([$hash]);
    if ($s->fetch()) {
        send_admin('sendMessage',['chat_id'=>$chat,'text'=>'⚠️ This bot is already connected.']);
        return;
    }

    $s=$pdo->prepare('INSERT INTO bots(token_enc,token_hash,bot_id,username,first_name) VALUES(?,?,?,?,?)');
    $s->execute([
        encrypt_token($token),$hash,(int)$r['id'],$r['username']??null,$r['first_name']??null
    ]);
    $botId=(int)$pdo->lastInsertId();

    $url=public_webhook_url($botId);
    $wh=telegram_call($token,'setWebhook',['url'=>$url,'secret_token'=>envv('TELEGRAM_WEBHOOK_SECRET','')]);
    clear_state($uid);

    $msg="✅ <b>BOT CONNECTED</b>\n\n🤖 @".h($r['username']??'unknown')."\n🆔 <code>".(int)$r['id']."</code>\n\n";
    $msg .= ($wh['ok']??false) ? "🔗 Webhook: <b>Active</b>" : "⚠️ Webhook failed: ".h($wh['description']??'Unknown');
    send_admin('sendMessage',['chat_id'=>$chat,'text'=>$msg,'parse_mode'=>'HTML']);
}

function public_webhook_url(int $botId): string {
    $domain=envv('RAILWAY_PUBLIC_DOMAIN');
    if (!$domain) throw new RuntimeException('Generate a Railway public domain first.');
    $base=str_starts_with($domain,'http')?$domain:'https://'.$domain;
    return rtrim($base,'/').'/child-webhook.php?bot='.$botId.'&secret='.rawurlencode(envv('TELEGRAM_WEBHOOK_SECRET',''));
}

function show_bots(int $chat): void {
    $rows=db()->query('SELECT * FROM bots WHERE active=1 ORDER BY id DESC')->fetchAll();
    if (!$rows) {
        send_admin('sendMessage',['chat_id'=>$chat,'text'=>'🤖 No connected bots yet.']);
        return;
    }
    $buttons=[];
    foreach($rows as $b) {
        $buttons[]=[button('🤖 @'.($b['username']?:$b['bot_id']),'bc:'.$b['id']),button('🗑','del:'.$b['id'])];
    }
    $buttons[]=[button('🏠 Menu','menu')];
    send_admin('sendMessage',[
        'chat_id'=>$chat,
        'text'=>"🤖 <b>CONNECTED BOTS</b>\n\nSelect a bot for broadcast or delete it.",
        'parse_mode'=>'HTML',
        'reply_markup'=>json_encode(keyboard($buttons),JSON_UNESCAPED_UNICODE)
    ]);
}

function show_bots_for_broadcast(int $chat): void { show_bots($chat); }

function start_broadcast(int $chat,int $uid,int $botId): void {
    if (!get_bot($botId)) return;
    set_state($uid,'broadcast_'.$botId);
    send_admin('sendMessage',[
        'chat_id'=>$chat,
        'text'=>"📢 <b>BROADCAST</b>\n\nSend one message now.\n\nSupported: text, photo, video, audio, document, voice, animation.\n\nYou can use HTML formatting. Inline buttons can be added later in the code/API layer.",
        'parse_mode'=>'HTML'
    ]);
}

function delete_bot(int $chat,int $botId): void {
    $b=get_bot($botId);
    if (!$b) return;
    $token=decrypt_token($b['token_enc']);
    telegram_call($token,'deleteWebhook',[]);
    db()->prepare('UPDATE bots SET active=0 WHERE id=?')->execute([$botId]);
    send_admin('sendMessage',['chat_id'=>$chat,'text'=>'🗑 Bot disconnected.']);
}

function set_state(int $uid,string $state): void {
    db()->exec("CREATE TABLE IF NOT EXISTS admin_state (user_id BIGINT PRIMARY KEY, state VARCHAR(255) NOT NULL)");
    $s=db()->prepare('INSERT INTO admin_state(user_id,state) VALUES(?,?) ON DUPLICATE KEY UPDATE state=VALUES(state)');
    $s->execute([$uid,$state]);
}
function get_state(int $uid): ?string {
    try {
        $s=db()->prepare('SELECT state FROM admin_state WHERE user_id=?');
        $s->execute([$uid]); $r=$s->fetch(); return $r['state']??null;
    } catch(Throwable $e) { return null; }
}
function clear_state(int $uid): void { db()->prepare('DELETE FROM admin_state WHERE user_id=?')->execute([$uid]); }

function child_webhook(): void {
    if (!secret_ok()) { http_response_code(403); exit; }
    $botId=(int)($_GET['bot']??0);
    $bot=get_bot($botId);
    if (!$bot) { http_response_code(404); exit; }
    $u=json_decode(file_get_contents('php://input'),true);
    if (!isset($u['message'])) return;
    $m=$u['message'];
    $chatId=(int)($m['chat']['id']??0);
    if (!$chatId) return;

    $s=db()->prepare('INSERT INTO users(bot_id,chat_id,first_name,username,blocked) VALUES(?,?,?,?,0)
      ON DUPLICATE KEY UPDATE first_name=VALUES(first_name), username=VALUES(username), blocked=0, last_seen=CURRENT_TIMESTAMP');
    $s->execute([$botId,$chatId,$m['from']['first_name']??null,$m['from']['username']??null]);
}

function process_master_broadcast_message(array $m,int $uid,int $chat,int $botId): void {
    $b=get_bot($botId); if(!$b) return;
    $token=decrypt_token($b['token_enc']);
    $payload=message_to_payload($m);
    if (!$payload) {
        send_admin('sendMessage',['chat_id'=>$chat,'text'=>'❌ This message type is not supported by this starter build.']);
        return;
    }
    $users=db()->prepare('SELECT chat_id FROM users WHERE bot_id=? AND blocked=0');
    $users->execute([$botId]);
    $all=$users->fetchAll();
    $total=count($all); $ok=0; $fail=0;

    foreach($all as $row) {
        $r=tg_send_payload($token,(int)$row['chat_id'],$payload);
        if (($r['ok']??false)) $ok++;
        else {
            $fail++;
            $desc=strtolower((string)($r['description']??''));
            if (str_contains($desc,'blocked') || str_contains($desc,'deactivated') || str_contains($desc,'chat not found')) {
                db()->prepare('UPDATE users SET blocked=1 WHERE bot_id=? AND chat_id=?')->execute([$botId,$row['chat_id']]);
            }
        }
        usleep(45000);
    }
    db()->prepare('INSERT INTO broadcasts(bot_id,type,payload,status,total,success_count,fail_count,finished_at) VALUES(?,?,?,?,?,?,?,NOW())')
      ->execute([$botId,$payload['type'],json_encode($payload,JSON_UNESCAPED_UNICODE),'done',$total,$ok,$fail]);

    send_admin('sendMessage',[
        'chat_id'=>$chat,
        'text'=>"📢 <b>BROADCAST COMPLETED</b>\n\n👥 Total: <b>{$total}</b>\n✅ Success: <b>{$ok}</b>\n❌ Failed: <b>{$fail}</b>",
        'parse_mode'=>'HTML'
    ]);
}

function message_to_payload(array $m): ?array {
    $buttons=$m['reply_markup']??null;
    if(isset($m['text'])) return ['type'=>'text','text'=>$m['text'],'reply_markup'=>$buttons];
    if(isset($m['photo'])) {
        $p=end($m['photo']);
        return ['type'=>'photo','photo'=>$p['file_id'],'caption'=>$m['caption']??'','reply_markup'=>$buttons];
    }
    foreach(['video','audio','document','voice','animation'] as $t) {
        if(isset($m[$t])) return ['type'=>$t,$t=>$m[$t]['file_id'],'caption'=>$m['caption']??'','reply_markup'=>$buttons];
    }
    return null;
}
