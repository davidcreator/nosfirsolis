<?php

declare(strict_types=1);

define('AREA', 'install');
define('DIR_ROOT', dirname(__DIR__));

require_once DIR_ROOT . '/config.php';

$installConfig = __DIR__ . '/config.php';
if (is_file($installConfig)) {
    require_once $installConfig;
}

require_once DIR_ROOT . '/system/Engine/Startup.php';

$app = new \System\Engine\Application('install');
$app->run();
