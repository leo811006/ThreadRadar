<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannelInterface;
use App\Data\NotificationPayload;
use App\Exceptions\NotificationDeliveryException;
use App\Mail\HotPostNotificationMail;
use Illuminate\Support\Facades\Mail;

class EmailChannel implements NotificationChannelInterface
{
    public function send(NotificationPayload $payload, array $config): void
    {
        $to = $config['to'] ?? null;

        if (! $to) {
            throw new NotificationDeliveryException('Email "to" address is not configured.');
        }

        Mail::to($to)->send(new HotPostNotificationMail($payload));
    }
}
