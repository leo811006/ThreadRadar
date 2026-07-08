<?php

namespace App\Services;

use App\Contracts\NotificationChannelInterface;
use App\Data\NotificationPayload;
use App\Exceptions\NotificationDeliveryException;
use App\Models\Post;
use App\Models\PostKeywordMatch;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * 「首次達標只通知一次」的權威實作：以 post_keyword_matches.notified_at 作為持久化去重標記，
 * 不依賴 Queue 層面的去重（Queue 訊息可能重複投遞，不可靠）。
 */
class NotificationService
{
    /**
     * @param  array<string, NotificationChannelInterface>  $channels  channel_type => adapter 實例
     */
    public function __construct(
        private readonly array $channels,
    ) {}

    /**
     * 若該文章對該關鍵字尚未通知過，依關鍵字設定的所有啟用管道發送通知，並標記為已通知。
     * 回傳 true 表示本次確實發送了通知；false 表示已通知過而跳過。
     */
    public function notifyIfNotAlreadyNotified(Post $post, PostKeywordMatch $match): bool
    {
        if ($match->notified_at !== null) {
            return false;
        }

        $keyword = $match->keyword;
        $payload = $this->buildPayload($post, $keyword->name);

        foreach ($keyword->notificationChannels()->where('is_active', true)->get() as $channelConfig) {
            $this->dispatchToChannel($channelConfig->channel_type, $payload, $channelConfig->config, $match);
        }

        $match->notified_at = Date::now();
        $match->save();

        return true;
    }

    private function dispatchToChannel(string $channelType, NotificationPayload $payload, array $config, PostKeywordMatch $match): void
    {
        $adapter = $this->channels[$channelType] ?? null;

        if ($adapter === null) {
            throw new InvalidArgumentException("Unsupported notification channel: {$channelType}");
        }

        try {
            $adapter->send($payload, $config);

            $match->notificationLogs()->create([
                'channel_type' => $channelType,
                'status' => 'sent',
                'payload' => $payload->toArray(),
                'sent_at' => Date::now(),
            ]);
        } catch (NotificationDeliveryException $e) {
            Log::warning("Notification delivery failed via {$channelType}: {$e->getMessage()}");

            $match->notificationLogs()->create([
                'channel_type' => $channelType,
                'status' => 'failed',
                'payload' => $payload->toArray(),
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function buildPayload(Post $post, string $keywordName): NotificationPayload
    {
        return new NotificationPayload(
            keywordName: $keywordName,
            authorName: $post->author_name,
            authorUsername: $post->author_username,
            contentSummary: mb_substr($post->content, 0, 200),
            threadsUrl: $post->threads_url,
            viewsCount: $post->views_count,
            likesCount: $post->likes_count,
            repliesCount: $post->replies_count,
            repostsCount: $post->reposts_count,
            quotesCount: $post->quotes_count,
        );
    }
}
