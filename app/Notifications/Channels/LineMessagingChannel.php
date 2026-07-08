<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannelInterface;
use App\Data\NotificationPayload;
use App\Exceptions\NotificationDeliveryException;
use Illuminate\Support\Facades\Http;

/**
 * LINE Notify 已於 2025-03-31 終止服務（官方公告），故僅實作 LINE Messaging API（Push Message）。
 * 需在 config 提供 channel_access_token 與目的地 to（使用者/群組/聊天室 ID）。
 */
class LineMessagingChannel implements NotificationChannelInterface
{
    public function send(NotificationPayload $payload, array $config): void
    {
        $channelAccessToken = $config['channel_access_token'] ?? null;
        $to = $config['to'] ?? null;

        if (! $channelAccessToken || ! $to) {
            throw new NotificationDeliveryException('LINE channel_access_token or to is not configured.');
        }

        $text = "🔥 關鍵字「{$payload->keywordName}」發現熱門文章\n"
            . "作者: {$payload->authorName} (@{$payload->authorUsername})\n"
            . "內容: {$payload->contentSummary}\n"
            . "Views: {$payload->viewsCount} | Likes: {$payload->likesCount} | Replies: {$payload->repliesCount} | Reposts: {$payload->repostsCount} | Quotes: {$payload->quotesCount}\n"
            . $payload->threadsUrl;

        $response = Http::withToken($channelAccessToken)
            ->post('https://api.line.me/v2/bot/message/push', [
                'to' => $to,
                'messages' => [['type' => 'text', 'text' => $text]],
            ]);

        if ($response->failed()) {
            throw new NotificationDeliveryException("LINE Messaging API failed: HTTP {$response->status()}");
        }
    }
}
