<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host = envv('MYSQLHOST');
    $port = envv('MYSQLPORT', '3306');
    $name = envv('MYSQLDATABASE');
    $user = envv('MYSQLUSER');
    $pass = envv('MYSQLPASSWORD');

    if (!$host || !$name || !$user) {
        throw new RuntimeException('MySQL variables are missing.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function install_schema(): void {
    $pdo = db();
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS bots (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        token_enc TEXT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        bot_id BIGINT NULL,
        username VARCHAR(255) NULL,
        first_name VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        active TINYINT(1) NOT NULL DEFAULT 1
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

      CREATE TABLE IF NOT EXISTS users (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        bot_id INT UNSIGNED NOT NULL,
        chat_id BIGINT NOT NULL,
        first_name VARCHAR(255) NULL,
        username VARCHAR(255) NULL,
        blocked TINYINT(1) NOT NULL DEFAULT 0,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_bot_chat (bot_id, chat_id),
        KEY idx_bot_blocked (bot_id, blocked),
        CONSTRAINT fk_users_bot FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

      CREATE TABLE IF NOT EXISTS broadcasts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        bot_id INT UNSIGNED NOT NULL,
        type VARCHAR(32) NOT NULL,
        payload LONGTEXT NOT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'queued',
        total INT NOT NULL DEFAULT 0,
        success_count INT NOT NULL DEFAULT 0,
        fail_count INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        finished_at TIMESTAMP NULL,
        KEY idx_broadcast_bot (bot_id, created_at),
        CONSTRAINT fk_broadcast_bot FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

function encrypt_token(string $token): string {
    $key = hash('sha256', envv('TOKEN_ENCRYPTION_KEY', ''), true);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($token, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) throw new RuntimeException('Token encryption failed.');
    return base64_encode($iv . $cipher);
}

function decrypt_token(string $blob): string {
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 17) throw new RuntimeException('Bad encrypted token.');
    $key = hash('sha256', envv('TOKEN_ENCRYPTION_KEY', ''), true);
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $token = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($token === false) throw new RuntimeException('Token decryption failed.');
    return $token;
}

function get_bot(int $id): ?array {
    $s = db()->prepare('SELECT * FROM bots WHERE id=? AND active=1');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}
