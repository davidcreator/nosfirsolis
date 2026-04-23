<?php

declare(strict_types=1);

if (!defined('DIR_ROOT')) {
    define('DIR_ROOT', dirname(__DIR__, 2));
}

if (!defined('DIR_SYSTEM')) {
    define('DIR_SYSTEM', DIR_ROOT . DIRECTORY_SEPARATOR . 'system');
}

require_once DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Helper' . DIRECTORY_SEPARATOR . 'common.php';

$composerAutoload = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

require_once DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Engine' . DIRECTORY_SEPARATOR . 'Autoloader.php';

$autoloader = new \System\Engine\Autoloader();
$autoloader->addNamespace('System\\Engine', DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Engine');
$autoloader->addNamespace('System\\Library', DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Library');
$autoloader->addNamespace('System\\Webhook', DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Webhook');
$autoloader->addNamespace('Admin\\Controller', DIR_ROOT . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'Controller');
$autoloader->addNamespace('Admin\\Model', DIR_ROOT . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'Model');
$autoloader->addNamespace('Client\\Controller', DIR_ROOT . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'Controller');
$autoloader->addNamespace('Client\\Model', DIR_ROOT . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'Model');
$autoloader->addNamespace('Install\\Controller', DIR_ROOT . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'Controller');
$autoloader->addNamespace('Install\\Model', DIR_ROOT . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'Model');
$autoloader->register();
