<?php

namespace System\Library;

use System\Library\HostGuard;

trait CampaignTrackingUrlHelpersTrait
{
    private function generateShortCode(): string
    {
        $alphabet = '23456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $max = strlen($alphabet) - 1;

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = '';
            for ($i = 0; $i < 8; $i++) {
                $candidate .= $alphabet[random_int(0, $max)];
            }

            $existing = $this->db()->fetch(
                'SELECT id FROM campaign_tracking_links WHERE short_code = :short_code LIMIT 1',
                ['short_code' => $candidate]
            );
            if (!$existing) {
                return $candidate;
            }
        }

        return bin2hex(random_bytes(6));
    }

    private function appendQueryParams(string $url, array $params): string
    {
        if (empty($params)) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $currentParams = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $currentParams);
        }
        foreach ($params as $key => $value) {
            $currentParams[$key] = $value;
        }
        $parts['query'] = http_build_query($currentParams);

        $result = '';
        if (!empty($parts['scheme'])) {
            $result .= $parts['scheme'] . '://';
        }
        if (!empty($parts['user'])) {
            $result .= $parts['user'];
            if (!empty($parts['pass'])) {
                $result .= ':' . $parts['pass'];
            }
            $result .= '@';
        }
        if (!empty($parts['host'])) {
            $result .= $parts['host'];
        }
        if (!empty($parts['port'])) {
            $result .= ':' . $parts['port'];
        }
        $result .= $parts['path'] ?? '';
        if (!empty($parts['query'])) {
            $result .= '?' . $parts['query'];
        }
        if (!empty($parts['fragment'])) {
            $result .= '#' . $parts['fragment'];
        }

        return $result !== '' ? $result : $url;
    }

    private function buildInternalShortUrl(string $shortCode): string
    {
        $server = $this->requestServerContext();
        $https = !empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off';
        $https = $https || (int) ($server['SERVER_PORT'] ?? 0) === 443;
        $scheme = $https ? 'https' : 'http';
        $host = HostGuard::effectiveHost(
            $server,
            (array) $this->config()?->get('security.allowed_hosts', []),
            (string) $this->config()?->get('app.base_url', '')
        );

        return $scheme . '://' . $host . route_url('tracking/redirect/' . rawurlencode($shortCode));
    }

    private function shortenWithBitly(string $url): ?string
    {
        $token = trim((string) $this->config()?->get('integrations.tracking.bitly_access_token', ''));
        if ($token === '') {
            return null;
        }

        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init('https://api-ssl.bitly.com/v4/shorten');
        $payload = json_encode(['long_url' => $url], JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) {
            return null;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $bitlink = trim((string) ($decoded['link'] ?? ''));
        if (!$this->isValidUrl($bitlink)) {
            return null;
        }

        return $bitlink;
    }

    private function isValidUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    private function normalizeText(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, 160);
    }

    private function requestServerContext(): array
    {
        $request = $this->registry->get('request');
        if (!is_object($request) || !isset($request->server) || !is_array($request->server)) {
            return [];
        }

        return $request->server;
    }
}
