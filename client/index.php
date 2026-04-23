<?php

declare(strict_types=1);

define('AREA', 'client');
define('DIR_ROOT', dirname(__DIR__));

require_once DIR_ROOT . '/config.php';

$clientConfig = __DIR__ . '/config.php';
if (is_file($clientConfig)) {
    require_once $clientConfig;
}

require_once DIR_ROOT . '/system/Engine/Startup.php';

$app = new \System\Engine\Application('client');
$app->run();
