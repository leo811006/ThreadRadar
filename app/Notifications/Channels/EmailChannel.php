<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannelInterface;
use App\Data\NotificationPayload;
use App\Exceptions\NotificationDeliveryException;
use App\Mail\HotPostNotificationMail;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailChannel implements NotificationChannelInterface
{
    public function send(NotificationPayload $payload, array $config): void
    {
        $to = $config['to'] ?? null;

        if (! $to) {
            throw new NotificationDeliveryException('Email "to" address is not configured.');
        }

        try {
            Mail::to($to)->send(new HotPostNotificationMail($payload));
        } catch (Throwable $e) {
            throw new NotificationDeliveryException("Email delivery failed: {$e->getMessage()}", previous: $e);
        }
    }
}
