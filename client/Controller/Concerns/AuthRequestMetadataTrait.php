<?php

namespace Client\Controller\Concerns;

trait AuthRequestMetadataTrait
{
    private function requestClientIp(): string
    {
        $request = $this->registry->get('request');
        $server = is_object($request) && isset($request->server) && is_array($request->server)
            ? $request->server
            : [];
        $ip = (string) ($server['REMOTE_ADDR'] ?? '');

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    private function requestUserAgent(): string
    {
        $request = $this->registry->get('request');
        $server = is_object($request) && isset($request->server) && is_array($request->server)
            ? $request->server
            : [];

        return mb_substr((string) ($server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
