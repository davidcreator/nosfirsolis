<?php

namespace System\Webhook;

abstract class WebhookBase
{
    public function validateSignature(string $payload, string $signature, string $secret): bool
    {
        $hash = hash_hmac('sha256', $payload, $secret);

        return hash_equals($hash, $signature);
    }

    abstract public function handle(array $payload): array;
}
