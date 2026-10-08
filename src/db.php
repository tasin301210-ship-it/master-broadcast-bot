<?php
declare(strict_types=1);

function railway_env(string $key, string $default = ''): string
{
    $value = getenv($key);

    if ($value === false || $value === '') {
        return $default;
    }

    return (string)$value;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = railway_env('MYSQLHOST');
    $port = railway_env('MYSQLPORT', '3306');
    $database = railway_env('MYSQLDATABASE');
    $username = railway_env('MYSQLUSER');
    $password = railway_env('MYSQLPASSWORD');

    if ($host === '') {
        throw new RuntimeException(
            'MYSQLHOST is missing from Railway Variables.'
        );
    }

    if ($database === '') {
        throw new RuntimeException(
            'MYSQLDATABASE is missing from Railway Variables.'
        );
    }

    if ($username === '') {
        throw new RuntimeException(
            'MYSQLUSER is missing from Railway Variables.'
        );
    }

    $dsn =
        'mysql:host=' . $host .
        ';port=' . $port .
        ';dbname=' . $database .
        ';charset=utf8mb4';

    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    return $pdo;
}

function install_schema(): void
{
    $pdo = db();

    $queries = [];

    $queries[] = "
        CREATE TABLE IF NOT EXISTS bots (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bot_id BIGINT NOT NULL UNIQUE,
            username VARCHAR(255) NOT NULL,
            first_name VARCHAR(255) NULL,
            token_enc TEXT NOT NULL,
            webhook_secret VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bot_id BIGINT NOT NULL,
            telegram_id BIGINT NOT NULL,
            username VARCHAR(255) NULL,
            first_name VARCHAR(255) NULL,
            is_blocked TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_bot_user (bot_id, telegram_id),
            KEY idx_bot (bot_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS broadcasts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bot_id BIGINT NOT NULL,
            admin_id BIGINT NOT NULL,
            content_type VARCHAR(50) NOT NULL,
            total INT UNSIGNED NOT NULL DEFAULT 0,
            success INT UNSIGNED NOT NULL DEFAULT 0,
            failed INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $queries[] = "
        CREATE TABLE IF NOT EXISTS admin_state (
            admin_id BIGINT PRIMARY KEY,
            state VARCHAR(50) NOT NULL,
            bot_id BIGINT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    foreach ($queries as $query) {
        $pdo->exec($query);
    }
}

function encrypt_token(string $token): string
{
    $secret = railway_env(
        'TOKEN_ENCRYPTION_KEY',
        'change-this-secret'
    );

    $key = hash(
        'sha256',
        $secret,
        true
    );

    $iv = random_bytes(16);

    $encrypted = openssl_encrypt(
        $token,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($encrypted === false) {
        throw new RuntimeException(
            'Could not encrypt bot token.'
        );
    }

    return base64_encode($iv . $encrypted);
}

function decrypt_token(string $data): string
{
    $raw = base64_decode($data, true);

    if ($raw === false || strlen($raw) < 17) {
        throw new RuntimeException(
            'Stored bot token is invalid.'
        );
    }

    $secret = railway_env(
        'TOKEN_ENCRYPTION_KEY',
        'change-this-secret'
    );

    $key = hash(
        'sha256',
        $secret,
        true
    );

    $iv = substr($raw, 0, 16);
    $encrypted = substr($raw, 16);

    $token = openssl_decrypt(
        $encrypted,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($token === false) {
        throw new RuntimeException(
            'Could not decrypt bot token.'
        );
    }

    return $token;
}
