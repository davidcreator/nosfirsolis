<?php

namespace Client\Controller\Concerns;

trait SocialConnectionSupportTrait
{
    private function accessKeyDocsByPlatform(): array
    {
        return [
            'instagram' => [
                'label' => 'Meta for Developers (Instagram)',
                'url' => 'https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/get-started',
            ],
            'facebook' => [
                'label' => 'Meta for Developers (Facebook)',
                'url' => 'https://developers.facebook.com/docs/graph-api/get-started',
            ],
            'linkedin' => [
                'label' => 'LinkedIn Developers',
                'url' => 'https://learn.microsoft.com/en-us/linkedin/shared/authentication/authentication',
            ],
            'tiktok' => [
                'label' => 'TikTok Developers',
                'url' => 'https://developers.tiktok.com/doc/login-kit-web',
            ],
            'x-twitter' => [
                'label' => 'X Developer Platform',
                'url' => 'https://developer.x.com/en/docs/authentication/oauth-2-0',
            ],
            'pinterest' => [
                'label' => 'Pinterest Developers',
                'url' => 'https://developers.pinterest.com/docs/getting-started/introduction',
            ],
            'threads' => [
                'label' => 'Meta for Developers (Threads)',
                'url' => 'https://developers.facebook.com/docs/threads',
            ],
            'youtube' => [
                'label' => 'Google OAuth / YouTube API',
                'url' => 'https://developers.google.com/youtube/v3/guides/auth/server-side-web-apps',
            ],
            'vimeo' => [
                'label' => 'Vimeo Developer Apps',
                'url' => 'https://developer.vimeo.com/apps',
            ],
            'blog' => [
                'label' => 'WordPress Application Passwords',
                'url' => 'https://wordpress.org/documentation/article/application-passwords/',
            ],
            'podcast' => [
                'label' => 'RSS Specification',
                'url' => 'https://www.rssboard.org/rss-specification',
            ],
            'email-marketing' => [
                'label' => 'Google App Passwords',
                'url' => 'https://support.google.com/accounts/answer/185833',
            ],
        ];
    }

    private function validateManualToken(array $platform, string $accessToken, ?string $tokenExpiresAt): array
    {
        $checkedAt = $this->formatDateTime();
        $token = trim($accessToken);
        if ($token === '') {
            return [
                'status' => 'invalid',
                'label' => $this->t('social.validation_invalid', 'Invalida ou rejeitada'),
                'message' => $this->t('social.validation_empty', 'A chave/token esta vazia.'),
                'checked_at' => $checkedAt,
                'method' => 'local',
            ];
        }

        if (strlen($token) < 10) {
            return [
                'status' => 'invalid',
                'label' => $this->t('social.validation_invalid', 'Invalida ou rejeitada'),
                'message' => $this->t('social.validation_too_short', 'Token muito curto para ser valido.'),
                'checked_at' => $checkedAt,
                'method' => 'local',
            ];
        }

        if ($tokenExpiresAt !== null && trim($tokenExpiresAt) !== '') {
            $expiresTs = $this->parseDateToTimestamp($tokenExpiresAt);
            if ($expiresTs !== null && $expiresTs <= $this->nowUnixTime()) {
                return [
                    'status' => 'invalid',
                    'label' => $this->t('social.validation_invalid', 'Invalida ou rejeitada'),
                    'message' => $this->t('social.validation_expired', 'Token expirado. Gere uma nova chave e tente novamente.'),
                    'checked_at' => $checkedAt,
                    'method' => 'local',
                ];
            }
        }

        $kind = strtolower(trim((string) ($platform['kind'] ?? 'manual')));
        $profileUrl = trim((string) ($platform['profile_url'] ?? ''));
        if ($kind === 'oauth2' && $profileUrl !== '') {
            $oauth = $this->socialAuthService();
            $profile = $oauth->fetchProfile($platform, $token);
            if (!empty($profile['ok'])) {
                return [
                    'status' => 'valid',
                    'label' => $this->t('social.validation_valid', 'Aprovada e valida'),
                    'message' => $this->t('social.validation_valid_message', 'Token validado com sucesso na API da plataforma.'),
                    'checked_at' => $checkedAt,
                    'method' => 'api_profile',
                ];
            }

            $details = strtolower(trim((string) ($profile['details'] ?? $profile['error'] ?? '')));
            $looksInvalid = str_contains($details, '401')
                || str_contains($details, '403')
                || str_contains($details, 'invalid')
                || str_contains($details, 'expired')
                || str_contains($details, 'revoked')
                || str_contains($details, 'unauthorized');

            if ($looksInvalid) {
                return [
                    'status' => 'invalid',
                    'label' => $this->t('social.validation_invalid', 'Invalida ou rejeitada'),
                    'message' => $this->t('social.validation_rejected', 'A plataforma rejeitou esta chave/token.'),
                    'checked_at' => $checkedAt,
                    'method' => 'api_profile',
                ];
            }

            return [
                'status' => 'unknown',
                'label' => $this->t('social.validation_unknown', 'Sem confirmacao automatica'),
                'message' => $this->t('social.validation_unreachable', 'Nao foi possivel confirmar a chave automaticamente agora.'),
                'checked_at' => $checkedAt,
                'method' => 'api_profile',
            ];
        }

        return [
            'status' => 'unknown',
            'label' => $this->t('social.validation_unknown', 'Sem confirmacao automatica'),
            'message' => $this->t('social.validation_manual_only', 'Esta plataforma exige validacao manual da chave no painel oficial.'),
            'checked_at' => $checkedAt,
            'method' => 'local',
        ];
    }

    private function absoluteRoute(string $route): string
    {
        return $this->absoluteRouteUrl($route);
    }
}
