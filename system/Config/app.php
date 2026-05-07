<?php

return [
    'name' => 'Solis',
    'environment' => 'development',
    'timezone' => 'America/Sao_Paulo',
    'default_language' => 'en-us',
    'session_name' => 'nsplanner_session',
    'installed' => false,
    'areas' => [
        'admin' => [
            'default_controller' => 'dashboard',
            'default_action' => 'index',
            'login_route' => 'auth/login',
        ],
        'client' => [
            'default_controller' => 'dashboard',
            'default_action' => 'index',
            'login_route' => 'auth/login',
        ],
        'install' => [
            'default_controller' => 'index',
            'default_action' => 'index',
            'login_route' => '',
        ],
    ],
    'security' => [
        'csrf_token_name' => '_token',
        'token_cipher_key' => '',
        'allowed_hosts' => ['localhost', '127.0.0.1', '::1'],
        'host_guard_compatibility_mode' => false,
        'runtime_schema_mutations' => false,
        'auth' => [
            'window_minutes' => 15,
            'block_minutes' => 20,
            'max_attempts_per_ip' => 12,
            'max_attempts_per_user' => 6,
            'session_ttl_minutes' => 720,
            'fail_open_on_security_error' => false,
        ],
        'headers' => [
            'enabled' => true,
            'x_content_type_options' => 'nosniff',
            'x_frame_options' => 'SAMEORIGIN',
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'permissions_policy' => 'geolocation=(), camera=(), microphone=()',
            'x_permitted_cross_domain_policies' => 'none',
            'content_security_policy' => "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline' https:; connect-src 'self' https:; font-src 'self' data: https:; frame-src 'self' https:;",
            'hsts' => [
                'enabled' => false,
                'max_age' => 31536000,
                'include_subdomains' => true,
                'preload' => false,
            ],
        ],
    ],
    'features' => [
        'export_pdf' => false,
        'export_csv' => false,
        'print_view' => true,
        'api_ready' => true,
        'webhook_ready' => true,
    ],
    'integrations' => [
        'social' => [
            'instagram' => ['enabled' => true, 'client_id' => '', 'client_secret' => '', 'scopes' => 'instagram_basic,pages_show_list,instagram_content_publish'],
            'facebook' => ['enabled' => true, 'client_id' => '', 'client_secret' => '', 'scopes' => 'pages_manage_posts,pages_read_engagement,public_profile,email'],
            'linkedin' => ['enabled' => true, 'client_id' => '', 'client_secret' => '', 'scopes' => 'openid profile w_member_social email'],
            'tiktok' => ['enabled' => true, 'client_id' => '', 'client_secret' => '', 'scopes' => 'user.info.basic,video.publish'],
            'x-twitter' => ['enabled' => true, 'client_id' => '', 'client_secret' => '', 'scopes' => 'tweet.read tweet.write users.read offline.access'],
            'pinterest' => ['enabled' => true, 'client_id' => '', 'client_secret' => '', 'scopes' => 'pins:read,pins:write,boards:read'],
            'threads' => ['enabled' => true, 'client_id' => '', 'client_secret' => '', 'scopes' => 'threads_basic,threads_content_publish'],
            'youtube' => ['enabled' => true, 'client_id' => '', 'client_secret' => '', 'scopes' => 'openid profile https://www.googleapis.com/auth/youtube.upload'],
            'vimeo' => ['enabled' => true, 'client_id' => '', 'client_secret' => '', 'scopes' => 'public private create edit upload'],
            'blog' => ['enabled' => true],
            'podcast' => ['enabled' => true],
            'email-marketing' => ['enabled' => true],
        ],
        'social_publisher' => [
            'dry_run' => true,
            'linkedin_version' => '202603',
        ],
        'billing' => [
            'currency' => 'BRL',
            'mock_auto_approve' => false,
        ],
        'tracking' => [
            'bitly_access_token' => '',
        ],
        'observability' => [
            'sentry_enabled' => false,
            'sentry_dsn' => '',
        ],
    ],
];
