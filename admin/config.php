<?php

if (!defined('DIR_ADMIN')) {
    define('DIR_ADMIN', __DIR__);
}

if (!defined('DIR_ROOT')) {
    define('DIR_ROOT', dirname(__DIR__));
}

return [
    'admin' => [
        'name' => 'Painel Administrativo',
        'base_url' => '',
        'reinstall_permission' => 'admin.install.reinstall',
    ],
    'routes' => [
        'public_routes' => [
            'auth/login',
            'auth/authenticate',
        ],
        'login_redirect' => 'auth/login',
    ],
];
