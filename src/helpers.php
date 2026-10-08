<?php
declare(strict_types=1);

if (!function_exists('envv')) {
    function envv(string $key, string $default = ''): string
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return (string)$value;
    }
}

function h(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_input(): array
{
    $raw = file_get_contents('php://input');

    if (!$raw) {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function is_admin(int $userId): bool
{
    $ids = array_filter(
        array_map(
            'trim',
            explode(',', envv('ADMIN_IDS', ''))
        )
    );

    return in_array((string)$userId, $ids, true);
}

function webhook_secret_ok(): bool
{
    $secret = envv('TELEGRAM_WEBHOOK_SECRET', '');

    if ($secret === '') {
        return true;
    }

    $headerSecret =
        $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

    if (
        $headerSecret !== '' &&
        hash_equals($secret, $headerSecret)
    ) {
        return true;
    }

    $querySecret = (string)($_GET['secret'] ?? '');

    return (
        $querySecret !== '' &&
        hash_equals($secret, $querySecret)
    );
}

function tg_button(
    string $text,
    ?string $url = null,
    ?string $callback = null
): array {
    $button = [
        'text' => $text
    ];

    if ($url !== null) {
        $button['url'] = $url;
    } elseif ($callback !== null) {
        $button['callback_data'] = $callback;
    }

    return $button;
}

function tg_keyboard(array $rows): array
{
    return [
        'keyboard' => $rows,
        'resize_keyboard' => true
    ];
}

function master_keyboard(): array
{
    return tg_keyboard([
        [
            tg_button('➕ Add Bot'),
            tg_button('🤖 Manage Bots')
        ],
        [
            tg_button('📢 Broadcast'),
            tg_button('📊 Stats')
        ]
    ]);
}

function back_keyboard(): array
{
    return tg_keyboard([
        [
            tg_button('🔙 Back')
        ]
    ]);
}
