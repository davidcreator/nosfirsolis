<?php

namespace System\Library;

class SocialAuthService
{
    public function buildAuthorizationUrl(array $platform, string $redirectUri, string $state, array &$statePayload = []): ?string
    {
        if (($platform['kind'] ?? '') !== 'oauth2') {
            return null;
        }

        $clientId = trim((string) ($platform['client_id'] ?? ''));
        $authUrl = trim((string) ($platform['auth_url'] ?? ''));
        if ($clientId === '' || $authUrl === '') {
            return null;
        }

        $scopeSeparator = (string) ($platform['scope_separator'] ?? ' ');
        $scopes = (array) ($platform['scopes'] ?? []);

        $query = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ];

        if ($scopes !== []) {
            $query['scope'] = implode($scopeSeparator, $scopes);
        }

        $usePkce = (bool) ($platform['use_pkce'] ?? false);
        if ($usePkce) {
            $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

            $query['code_challenge'] = $challenge;
            $query['code_challenge_method'] = 'S256';
            $statePayload['code_verifier'] = $verifier;
        }

        return $authUrl . (str_contains($authUrl, '?') ? '&' : '?') . http_build_query($query);
    }

    public function exchangeCode(
        array $platform,
        string $code,
        string $redirectUri,
        ?string $codeVerifier = null
    ): array {
        if (($platform['kind'] ?? '') !== 'oauth2') {
            return ['ok' => false, 'error' => 'invalid_platform_kind'];
        }

        $clientId = trim((string) ($platform['client_id'] ?? ''));
        $clientSecret = trim((string) ($platform['client_secret'] ?? ''));
        $tokenUrl = trim((string) ($platform['token_url'] ?? ''));
        $contentType = strtolower(trim((string) ($platform['token_content_type'] ?? 'form')));

        if ($clientId === '' || $tokenUrl === '') {
            return ['ok' => false, 'error' => 'missing_platform_credentials'];
        }

        $payload = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
        ];

        if ($clientSecret !== '') {
            $payload['client_secret'] = $clientSecret;
        }

        if ((bool) ($platform['use_pkce'] ?? false) && $codeVerifier !== null) {
            $payload['code_verifier'] = $codeVerifier;
        }

        $response = $this->httpPost($tokenUrl, $payload, $contentType);
        if (!$response['ok']) {
            return ['ok' => false, 'error' => 'token_exchange_failed', 'details' => $response['error'] ?? ''];
        }

        $data = $response['data'];
        if (!is_array($data) || empty($data['access_token'])) {
            return ['ok' => false, 'error' => 'invalid_token_response', 'details' => json_encode($data)];
        }

        $expiresAt = null;
        if (!empty($data['expires_in'])) {
            $expiresAt = date('Y-m-d H:i:s', time() + (int) $data['expires_in']);
        }

        $scopeRaw = (string) ($data['scope'] ?? '');
        if ($scopeRaw === '' && !empty($platform['scopes'])) {
            $scopeRaw = implode((string) ($platform['scope_separator'] ?? ' '), (array) $platform['scopes']);
        }

        return [
            'ok' => true,
            'access_token' => (string) $data['access_token'],
            'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
            'scope_text' => $scopeRaw,
            'expires_at' => $expiresAt,
            'raw' => $data,
        ];
    }

    public function fetchProfile(array $platform, string $accessToken): array
    {
        $profileUrl = trim((string) ($platform['profile_url'] ?? ''));
        if ($profileUrl === '') {
            return ['ok' => false, 'error' => 'profile_url_not_configured'];
        }

        $profileAuth = trim((string) ($platform['profile_auth'] ?? 'header'));
        if ($profileAuth === 'query') {
            $url = $profileUrl . (str_contains($profileUrl, '?') ? '&' : '?') . 'access_token=' . urlencode($accessToken);
            $response = $this->httpGet($url, []);
        } else {
            $response = $this->httpGet($profileUrl, ['Authorization: Bearer ' . $accessToken]);
        }

        if (!$response['ok']) {
            return ['ok' => false, 'error' => 'profile_fetch_failed', 'details' => $response['error'] ?? ''];
        }

        return ['ok' => true, 'data' => $response['data']];
    }

    private function httpPost(string $url, array $payload, string $contentType = 'form'): array
    {
        $headers = ['Accept: application/json'];
        $body = '';

        if ($contentType === 'json') {
            $headers[] = 'Content-Type: application/json';
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        } else {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $body = http_build_query($payload);
        }

        return $this->httpRequest('POST', $url, $headers, $body);
    }

    private function httpGet(string $url, array $headers = []): array
    {
        $baseHeaders = ['Accept: application/json'];
        $headers = array_merge($baseHeaders, $headers);

        return $this->httpRequest('GET', $url, $headers, null);
    }

    private function httpRequest(string $method, string $url, array $headers, ?string $body): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $raw = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw === false) {
                return ['ok' => false, 'error' => $error !== '' ? $error : 'curl_error'];
            }

            $data = json_decode((string) $raw, true);
            if (!is_array($data)) {
                parse_str((string) $raw, $parsed);
                $data = is_array($parsed) && $parsed !== [] ? $parsed : ['raw' => (string) $raw];
            }

            if ($status >= 400) {
                return ['ok' => false, 'error' => 'http_' . $status, 'data' => $data];
            }

            return ['ok' => true, 'data' => $data];
        }

        $opts = [
            'http' => [
                'method' => strtoupper($method),
                'timeout' => 30,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
            ],
        ];

        if ($body !== null) {
            $opts['http']['content'] = $body;
        }

        $context = stream_context_create($opts);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            return ['ok' => false, 'error' => 'http_request_failed'];
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            parse_str((string) $raw, $parsed);
            $data = is_array($parsed) && $parsed !== [] ? $parsed : ['raw' => (string) $raw];
        }

        return ['ok' => true, 'data' => $data];
    }
}

