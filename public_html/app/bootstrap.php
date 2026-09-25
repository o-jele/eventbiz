<?php
// Bootstrap: config, session, helpers. Required by index.php and install.php.
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    $config = require APP_ROOT . '/config.example.php';
    $config['__missing'] = true;
} else {
    $config = require $configFile;
}

require_once APP_DIR . '/helpers.php';
require_once APP_DIR . '/db.php';
require_once APP_DIR . '/context.php';
require_once APP_DIR . '/auth.php';
