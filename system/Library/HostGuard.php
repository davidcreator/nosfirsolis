<?php

namespace System\Library;

final class HostGuard
{
    public static function normalizeHost(?string $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $host = strtolower(trim($value));
        if ($host === '') {
            return '';
        }

        $schemePos = strpos($host, '://');
        if ($schemePos !== false) {
            $parsedHost = parse_url($host, PHP_URL_HOST);
            $host = is_string($parsedHost) ? strtolower(trim($parsedHost)) : '';
            if ($host === '') {
                return '';
            }
        }

        $host = preg_replace('/[\/\?#].*$/', '', $host) ?? $host;
        if ($host === '') {
            return '';
        }

        if (str_starts_with($host, '[')) {
            $endPos = strpos($host, ']');
            if ($endPos === false) {
                return '';
            }

            $ipv6 = substr($host, 1, $endPos - 1);
            if (!is_string($ipv6) || !filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return '';
            }

            return $ipv6;
        }

        if (substr_count($host, ':') === 1) {
            [$candidateHost, $candidatePort] = explode(':', $host, 2);
            if (ctype_digit($candidatePort)) {
                $host = $candidateHost;
            }
        }

        if (str_contains($host, ':')) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? $host : '';
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $host;
        }

        $domainPattern = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/';
        if (preg_match($domainPattern, $host) !== 1) {
            return '';
        }

        return $host;
    }

    public static function requestHost(array $server): string
    {
        $rawHost = '';

        if (isset($server['HTTP_HOST']) && is_string($server['HTTP_HOST'])) {
            $rawHost = $server['HTTP_HOST'];
        } elseif (isset($server['SERVER_NAME']) && is_string($server['SERVER_NAME'])) {
            $rawHost = $server['SERVER_NAME'];
        }

        return self::normalizeHost($rawHost);
    }

    public static function baseUrlHost(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            return '';
        }

        $host = parse_url($baseUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        return self::normalizeHost($host);
    }

    public static function normalizedAllowedHosts(array $allowedHosts, string $baseUrl = ''): array
    {
        $normalized = [];

        foreach ($allowedHosts as $allowedHost) {
            if (!is_string($allowedHost)) {
                continue;
            }

            $host = self::normalizeHost($allowedHost);
            if ($host === '') {
                continue;
            }

            $normalized[$host] = true;
        }

        $baseHost = self::baseUrlHost($baseUrl);
        if ($baseHost !== '') {
            $normalized[$baseHost] = true;
        }

        return array_keys($normalized);
    }

    public static function isAllowedRequestHost(array $server, array $allowedHosts, string $baseUrl = ''): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }

        $requestHost = self::requestHost($server);
        if ($requestHost === '') {
            return false;
        }

        $normalizedAllowedHosts = self::normalizedAllowedHosts($allowedHosts, $baseUrl);
        if ($normalizedAllowedHosts === []) {
            return true;
        }

        return in_array($requestHost, $normalizedAllowedHosts, true);
    }

    public static function effectiveHost(array $server, array $allowedHosts, string $baseUrl = ''): string
    {
        $requestHost = self::requestHost($server);
        $normalizedAllowedHosts = self::normalizedAllowedHosts($allowedHosts, $baseUrl);

        if ($normalizedAllowedHosts !== []) {
            if ($requestHost !== '' && in_array($requestHost, $normalizedAllowedHosts, true)) {
                return $requestHost;
            }

            return (string) $normalizedAllowedHosts[0];
        }

        if ($requestHost !== '') {
            return $requestHost;
        }

        $baseHost = self::baseUrlHost($baseUrl);
        if ($baseHost !== '') {
            return $baseHost;
        }

        return 'localhost';
    }
}
