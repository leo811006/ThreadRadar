<?php

namespace App\Contracts;

use App\Data\NotificationPayload;

interface NotificationChannelInterface
{
    /**
     * @param  array<string, mixed>  $config  該管道的專屬設定（webhook URL、bot token 等）
     *
     * @throws \App\Exceptions\NotificationDeliveryException
     */
    public function send(NotificationPayload $payload, array $config): void;
}
