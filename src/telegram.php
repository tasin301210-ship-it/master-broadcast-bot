<?php
declare(strict_types=1);

function telegram_call(string $token, string $method, array $params = []): array {
    if ($token === '') return ['ok'=>false,'description'=>'Bot token is empty'];

    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) return ['ok'=>false,'description'=>$err ?: 'cURL error'];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['ok'=>false,'description'=>'Invalid Telegram response'];
}

function tg_send_text(string $token, int|string $chatId, string $text, ?array $replyMarkup=null): array {
    $p = ['chat_id'=>$chatId,'text'=>$text,'parse_mode'=>'HTML'];
    if ($replyMarkup) $p['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
    return telegram_call($token,'sendMessage',$p);
}

function tg_send_payload(string $token, int|string $chatId, array $m): array {
    $type = $m['type'] ?? 'text';
    $markup = $m['reply_markup'] ?? null;
    $extra = [];
    if ($markup) $extra['reply_markup'] = json_encode($markup, JSON_UNESCAPED_UNICODE);

    if ($type === 'text') {
        return telegram_call($token,'sendMessage', array_merge([
            'chat_id'=>$chatId, 'text'=>$m['text'] ?? '', 'parse_mode'=>'HTML'
        ], $extra));
    }
    $methodMap = [
        'photo'=>'sendPhoto','video'=>'sendVideo','audio'=>'sendAudio',
        'document'=>'sendDocument','voice'=>'sendVoice','animation'=>'sendAnimation'
    ];
    if (!isset($methodMap[$type])) return ['ok'=>false,'description'=>'Unsupported broadcast type'];
    $field = $type;
    $p = array_merge(['chat_id'=>$chatId, $field=>$m[$field] ?? ''], $extra);
    if (!empty($m['caption'])) { $p['caption']=$m['caption']; $p['parse_mode']='HTML'; }
    return telegram_call($token,$methodMap[$type],$p);
}
