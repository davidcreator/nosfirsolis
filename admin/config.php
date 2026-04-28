<?php

if (!defined('DIR_ADMIN')) {
    define('DIR_ADMIN', __DIR__);
}

if (!defined('DIR_ROOT')) {
    define('DIR_ROOT', dirname(__DIR__));
}

return array (
  'admin' => 
  array (
    'name' => 'Painel Administrativo',
    'base_url' => 'http://localhost/nosfirsolis/admin/',
    'reinstall_permission' => 'admin.install.reinstall',
  ),
  'routes' => 
  array (
    'public_routes' => 
    array (
      0 => 'auth/login',
      1 => 'auth/authenticate',
    ),
    'login_redirect' => 'auth/login',
  ),
);
