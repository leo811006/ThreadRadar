<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannelInterface;
use App\Data\NotificationPayload;
use App\Exceptions\NotificationDeliveryException;
use Illuminate\Support\Facades\Http;

class DiscordWebhookChannel implements NotificationChannelInterface
{
    public function send(NotificationPayload $payload, array $config): void
    {
        $webhookUrl = $config['webhook_url'] ?? null;

        if (! $webhookUrl) {
            throw new NotificationDeliveryException('Discord webhook_url is not configured.');
        }

        $response = Http::post($webhookUrl, [
            'embeds' => [[
                'title' => "🔥 關鍵字「{$payload->keywordName}」發現熱門文章",
                'url' => $payload->threadsUrl,
                'description' => $payload->contentSummary,
                'author' => ['name' => "{$payload->authorName} (@{$payload->authorUsername})"],
                'fields' => [
                    ['name' => 'Views', 'value' => (string) $payload->viewsCount, 'inline' => true],
                    ['name' => 'Likes', 'value' => (string) $payload->likesCount, 'inline' => true],
                    ['name' => 'Replies', 'value' => (string) $payload->repliesCount, 'inline' => true],
                    ['name' => 'Reposts', 'value' => (string) $payload->repostsCount, 'inline' => true],
                    ['name' => 'Quotes', 'value' => (string) $payload->quotesCount, 'inline' => true],
                ],
            ]],
        ]);

        if ($response->failed()) {
            throw new NotificationDeliveryException("Discord webhook failed: HTTP {$response->status()}");
        }
    }
}
