<?php

return array (
  'app' => 
  array (
    'name' => 'Solis',
    'environment' => 'development',
    'base_url' => 'http://localhost/nosfirsolis/',
    'installed' => true,
    'timezone' => 'America/Sao_Paulo',
    'default_language' => 'pt-br',
    'session_name' => 'nsplanner_session',
  ),
  'database' => 
  array (
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'nosfirsolis',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
  ),
  'security' => 
  array (
    'allow_reinstall' => false,
    'reinstall_key' => 'e81a0fb03318edc1a18970b0af3877adffe3d26629c920b8b17b7abb1c4529b1',
    'reinstall_permission' => 'admin.install.reinstall',
    'allowed_hosts' => 
    array (
      0 => 'localhost',
      1 => '127.0.0.1',
      2 => '::1',
    ),
  ),
);
