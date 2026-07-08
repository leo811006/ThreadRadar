<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannelInterface;
use App\Data\NotificationPayload;
use App\Exceptions\NotificationDeliveryException;
use Illuminate\Support\Facades\Http;

class TelegramBotChannel implements NotificationChannelInterface
{
    public function send(NotificationPayload $payload, array $config): void
    {
        $botToken = $config['bot_token'] ?? null;
        $chatId = $config['chat_id'] ?? null;

        if (! $botToken || ! $chatId) {
            throw new NotificationDeliveryException('Telegram bot_token or chat_id is not configured.');
        }

        $text = "🔥 關鍵字「{$payload->keywordName}」發現熱門文章\n"
            . "作者: {$payload->authorName} (@{$payload->authorUsername})\n"
            . "內容: {$payload->contentSummary}\n"
            . "Views: {$payload->viewsCount} | Likes: {$payload->likesCount} | Replies: {$payload->repliesCount} | Reposts: {$payload->repostsCount} | Quotes: {$payload->quotesCount}\n"
            . $payload->threadsUrl;

        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        if ($response->failed()) {
            throw new NotificationDeliveryException("Telegram API failed: HTTP {$response->status()}");
        }
    }
}
