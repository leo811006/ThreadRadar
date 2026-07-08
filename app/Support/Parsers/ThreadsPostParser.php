<?php

namespace App\Support\Parsers;

use App\Data\PostData;
use Carbon\CarbonImmutable;

/**
 * 將 Threads Keyword Search API 回傳的原始 JSON 結構正規化為 PostData。
 * 隔離「官方 API 回傳格式」與內部資料模型的耦合——若 Meta 調整回傳欄位，只需改這裡。
 */
class ThreadsPostParser
{
    /**
     * @param  array<string, mixed>  $raw  單筆貼文的官方 API 原始回傳資料
     */
    public function parse(array $raw): PostData
    {
        return new PostData(
            threadsUrl: $raw['permalink'],
            authorName: $raw['username'] ?? $raw['author']['name'] ?? '',
            authorUsername: $raw['username'] ?? $raw['author']['username'] ?? '',
            postedAt: CarbonImmutable::parse($raw['timestamp']),
            content: $raw['text'] ?? '',
            images: $this->extractMediaUrls($raw, 'IMAGE'),
            videos: $this->extractMediaUrls($raw, 'VIDEO'),
            viewsCount: (int) ($raw['views'] ?? 0),
            likesCount: (int) ($raw['likes'] ?? 0),
            repliesCount: (int) ($raw['replies'] ?? 0),
            repostsCount: (int) ($raw['reposts'] ?? 0),
            quotesCount: (int) ($raw['quotes'] ?? 0),
            isVerifiedAuthor: (bool) ($raw['is_verified'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return string[]
     */
    private function extractMediaUrls(array $raw, string $type): array
    {
        $attachments = $raw['media_attachments'] ?? [];

        return collect($attachments)
            ->where('type', $type)
            ->pluck('url')
            ->values()
            ->all();
    }
}
