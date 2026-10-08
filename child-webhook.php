<?php
declare(strict_types=1);

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/telegram.php';
require_once __DIR__ . '/src/app.php';

child_webhook();

echo 'OK';
