<?php
declare(strict_types=1);

function envv(string $key, ?string $default = null): ?string {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_admin(int $userId): bool {
    $ids = preg_split('/\s*,\s*/', envv('ADMIN_IDS', '') ?? '', -1, PREG_SPLIT_NO_EMPTY);
    return in_array((string)$userId, $ids, true);
}

function secret_ok(): bool {
    $secret = envv('WEBHOOK_SECRET', '');
    if ($secret === '') return true;
    return hash_equals($secret, (string)($_GET['secret'] ?? ''));
}

function tg_call(string $token, string $method, array $params = []): array {
    return telegram_call($token, $method, $params);
}

function send_admin(string $method, array $params): array {
    return tg_call(envv('MASTER_BOT_TOKEN', ''), $method, $params);
}

function button(string $text, string $data): array {
    return ['text' => $text, 'callback_data' => $data];
}

function keyboard(array $rows): array {
    return ['inline_keyboard' => $rows];
}
