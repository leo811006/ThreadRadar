<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannelInterface;
use App\Data\NotificationPayload;
use App\Exceptions\NotificationDeliveryException;
use Illuminate\Support\Facades\Http;

class SlackWebhookChannel implements NotificationChannelInterface
{
    public function send(NotificationPayload $payload, array $config): void
    {
        $webhookUrl = $config['webhook_url'] ?? null;

        if (! $webhookUrl) {
            throw new NotificationDeliveryException('Slack webhook_url is not configured.');
        }

        $response = Http::post($webhookUrl, [
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*🔥 關鍵字「{$payload->keywordName}」發現熱門文章*\n"
                            . "*作者*: {$payload->authorName} (@{$payload->authorUsername})\n"
                            . "*內容*: {$payload->contentSummary}\n"
                            . "*連結*: {$payload->threadsUrl}",
                    ],
                ],
                [
                    'type' => 'context',
                    'elements' => [[
                        'type' => 'mrkdwn',
                        'text' => "Views: {$payload->viewsCount} | Likes: {$payload->likesCount} | Replies: {$payload->repliesCount} | Reposts: {$payload->repostsCount} | Quotes: {$payload->quotesCount}",
                    ]],
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new NotificationDeliveryException("Slack webhook failed: HTTP {$response->status()}");
        }
    }
}
