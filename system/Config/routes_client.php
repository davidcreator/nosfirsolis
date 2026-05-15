<?php

return [
    'public_routes' => [
        'auth/login',
        'auth/register',
        'auth/createaccount',
        'auth/authenticate',
        'auth/forgotpassword',
        'auth/sendpasswordreset',
        'auth/forgotemail',
        'auth/sendemailrecovery',
        'auth/resetpassword',
        'auth/updatepassword',
        'tracking/redirect',
        'language/save',
    ],
    'login_redirect' => 'auth/login',
];
