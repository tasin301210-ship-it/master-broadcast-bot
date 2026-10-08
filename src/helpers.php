function secret_ok(): bool {
    $secret = envv('TELEGRAM_WEBHOOK_SECRET', '');

    if ($secret === '') {
        $secret = envv('WEBHOOK_SECRET', '');
    }

    if ($secret === '') {
        return true;
    }

    // Telegram official webhook secret header
    $headerSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

    if ($headerSecret !== '' && hash_equals($secret, $headerSecret)) {
        return true;
    }

    // Backward compatibility for ?secret=...
    $querySecret = (string)($_GET['secret'] ?? '');

    return $querySecret !== '' && hash_equals($secret, $querySecret);
}
