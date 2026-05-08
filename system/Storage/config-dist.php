<?php

return [
    'app' => [
        'name' => 'Solis',
        'environment' => 'production',
        'base_url' => 'https://example.com/solis/',
        'installed' => true,
        'timezone' => 'America/Sao_Paulo',
        'default_language' => 'en-us',
        'session_name' => 'nsplanner_session',
    ],
    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'CHANGE_ME_DATABASE',
        'username' => 'CHANGE_ME_USER',
        'password' => 'CHANGE_ME_PASSWORD',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],
    'security' => [
        'token_cipher_key' => 'CHANGE_ME_TOKEN_CIPHER_KEY',
        'allow_reinstall' => false,
        'reinstall_key' => 'CHANGE_ME_REINSTALL_KEY',
        'reinstall_permission' => 'admin.install.reinstall',
        'host_guard_compatibility_mode' => false,
        'allowed_hosts' => [
            'example.com',
        ],
    ],
];
