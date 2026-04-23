<?php

declare(strict_types=1);

define('AREA', 'admin');
define('DIR_ROOT', dirname(__DIR__));

require_once DIR_ROOT . '/config.php';
require_once __DIR__ . '/config.php';

require_once DIR_ROOT . '/system/Engine/Startup.php';

$app = new \System\Engine\Application('admin');
$app->run();
