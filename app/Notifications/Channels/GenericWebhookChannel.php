<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannelInterface;
use App\Data\NotificationPayload;
use App\Exceptions\NotificationDeliveryException;
use Illuminate\Support\Facades\Http;

class GenericWebhookChannel implements NotificationChannelInterface
{
    public function send(NotificationPayload $payload, array $config): void
    {
        $url = $config['url'] ?? null;

        if (! $url) {
            throw new NotificationDeliveryException('Webhook url is not configured.');
        }

        $response = Http::post($url, $payload->toArray());

        if ($response->failed()) {
            throw new NotificationDeliveryException("Generic webhook failed: HTTP {$response->status()}");
        }
    }
}
