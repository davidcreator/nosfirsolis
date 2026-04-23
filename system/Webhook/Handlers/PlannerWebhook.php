<?php

namespace System\Webhook\Handlers;

use System\Webhook\WebhookBase;

class PlannerWebhook extends WebhookBase
{
    public function handle(array $payload): array
    {
        return [
            'status' => 'accepted',
            'received_at' => date('c'),
            'payload_keys' => array_keys($payload),
        ];
    }
}