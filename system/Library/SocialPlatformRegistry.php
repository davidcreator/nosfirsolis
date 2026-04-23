<?php

namespace System\Library;

use System\Engine\Config;

class SocialPlatformRegistry
{
    public function __construct(private readonly Config $config)
    {
    }

    public function all(): array
    {
        $items = [];
        foreach ($this->defaults() as $slug => $platform) {
            $overrides = (array) $this->config->get('integrations.social.' . $slug, []);

            $platform['enabled'] = (bool) ($overrides['enabled'] ?? true);
            $platform['client_id'] = trim((string) ($overrides['client_id'] ?? ''));
            $platform['client_secret'] = trim((string) ($overrides['client_secret'] ?? ''));
            $platform['custom_auth_url'] = trim((string) ($overrides['auth_url'] ?? ''));
            $platform['custom_token_url'] = trim((string) ($overrides['token_url'] ?? ''));
            $platform['custom_profile_url'] = trim((string) ($overrides['profile_url'] ?? ''));
            $platform['scopes_override'] = trim((string) ($overrides['scopes'] ?? ''));

            if ($platform['custom_auth_url'] !== '') {
                $platform['auth_url'] = $platform['custom_auth_url'];
            }
            if ($platform['custom_token_url'] !== '') {
                $platform['token_url'] = $platform['custom_token_url'];
            }
            if ($platform['custom_profile_url'] !== '') {
                $platform['profile_url'] = $platform['custom_profile_url'];
            }

            if ($platform['scopes_override'] !== '') {
                $separator = $platform['scope_separator'] ?? ' ';
                $scopes = array_map('trim', explode($separator, $platform['scopes_override']));
                $platform['scopes'] = array_values(array_filter($scopes, static fn ($v) => $v !== ''));
            }

            $platform['slug'] = $slug;
            $items[$slug] = $platform;
        }

        return $items;
    }

    public function get(string $slug): ?array
    {
        $slug = strtolower(trim($slug));
        $all = $this->all();

        return $all[$slug] ?? null;
    }

    private function defaults(): array
    {
        return [
            'instagram' => [
                'name' => 'Instagram',
                'kind' => 'oauth2',
                'auth_url' => 'https://www.facebook.com/v20.0/dialog/oauth',
                'token_url' => 'https://graph.facebook.com/v20.0/oauth/access_token',
                'profile_url' => 'https://graph.facebook.com/me?fields=id,name',
                'profile_auth' => 'query',
                'scopes' => ['instagram_basic', 'pages_show_list', 'instagram_content_publish'],
                'scope_separator' => ',',
                'use_pkce' => false,
                'token_content_type' => 'form',
            ],
            'facebook' => [
                'name' => 'Facebook',
                'kind' => 'oauth2',
                'auth_url' => 'https://www.facebook.com/v20.0/dialog/oauth',
                'token_url' => 'https://graph.facebook.com/v20.0/oauth/access_token',
                'profile_url' => 'https://graph.facebook.com/me?fields=id,name',
                'profile_auth' => 'query',
                'scopes' => ['pages_manage_posts', 'pages_read_engagement', 'public_profile', 'email'],
                'scope_separator' => ',',
                'use_pkce' => false,
                'token_content_type' => 'form',
            ],
            'linkedin' => [
                'name' => 'LinkedIn',
                'kind' => 'oauth2',
                'auth_url' => 'https://www.linkedin.com/oauth/v2/authorization',
                'token_url' => 'https://www.linkedin.com/oauth/v2/accessToken',
                'profile_url' => 'https://api.linkedin.com/v2/userinfo',
                'profile_auth' => 'header',
                'scopes' => ['openid', 'profile', 'w_member_social', 'email'],
                'scope_separator' => ' ',
                'use_pkce' => false,
                'token_content_type' => 'form',
            ],
            'tiktok' => [
                'name' => 'TikTok',
                'kind' => 'oauth2',
                'auth_url' => 'https://www.tiktok.com/v2/auth/authorize/',
                'token_url' => 'https://open.tiktokapis.com/v2/oauth/token/',
                'profile_url' => '',
                'profile_auth' => 'header',
                'scopes' => ['user.info.basic', 'video.publish'],
                'scope_separator' => ',',
                'use_pkce' => true,
                'token_content_type' => 'form',
            ],
            'x-twitter' => [
                'name' => 'X / Twitter',
                'kind' => 'oauth2',
                'auth_url' => 'https://twitter.com/i/oauth2/authorize',
                'token_url' => 'https://api.twitter.com/2/oauth2/token',
                'profile_url' => 'https://api.twitter.com/2/users/me',
                'profile_auth' => 'header',
                'scopes' => ['tweet.read', 'tweet.write', 'users.read', 'offline.access'],
                'scope_separator' => ' ',
                'use_pkce' => true,
                'token_content_type' => 'form',
            ],
            'pinterest' => [
                'name' => 'Pinterest',
                'kind' => 'oauth2',
                'auth_url' => 'https://www.pinterest.com/oauth/',
                'token_url' => 'https://api.pinterest.com/v5/oauth/token',
                'profile_url' => '',
                'profile_auth' => 'header',
                'scopes' => ['pins:read', 'pins:write', 'boards:read'],
                'scope_separator' => ',',
                'use_pkce' => false,
                'token_content_type' => 'form',
            ],
            'threads' => [
                'name' => 'Threads',
                'kind' => 'oauth2',
                'auth_url' => 'https://www.facebook.com/v20.0/dialog/oauth',
                'token_url' => 'https://graph.facebook.com/v20.0/oauth/access_token',
                'profile_url' => 'https://graph.facebook.com/me?fields=id,name',
                'profile_auth' => 'query',
                'scopes' => ['threads_basic', 'threads_content_publish'],
                'scope_separator' => ',',
                'use_pkce' => false,
                'token_content_type' => 'form',
            ],
            'youtube' => [
                'name' => 'YouTube',
                'kind' => 'oauth2',
                'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'profile_url' => 'https://www.googleapis.com/oauth2/v1/userinfo?alt=json',
                'profile_auth' => 'header',
                'scopes' => ['openid', 'profile', 'https://www.googleapis.com/auth/youtube.upload'],
                'scope_separator' => ' ',
                'use_pkce' => false,
                'token_content_type' => 'form',
            ],
            'vimeo' => [
                'name' => 'Vimeo',
                'kind' => 'oauth2',
                'auth_url' => 'https://api.vimeo.com/oauth/authorize',
                'token_url' => 'https://api.vimeo.com/oauth/access_token',
                'profile_url' => 'https://api.vimeo.com/me',
                'profile_auth' => 'header',
                'scopes' => ['public', 'private', 'create', 'edit', 'upload'],
                'scope_separator' => ' ',
                'use_pkce' => false,
                'token_content_type' => 'form',
            ],
            'blog' => [
                'name' => 'Blog',
                'kind' => 'manual',
                'scopes' => [],
                'scope_separator' => ' ',
                'use_pkce' => false,
                'token_content_type' => 'form',
            ],
            'podcast' => [
                'name' => 'Podcast',
                'kind' => 'manual',
                'scopes' => [],
                'scope_separator' => ' ',
                'use_pkce' => false,
                'token_content_type' => 'form',
            ],
            'email-marketing' => [
                'name' => 'E-mail marketing',
                'kind' => 'manual',
                'scopes' => [],
                'scope_separator' => ' ',
                'use_pkce' => false,
                'token_content_type' => 'form',
            ],
        ];
    }
}

