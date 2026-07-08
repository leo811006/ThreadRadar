<?php

namespace App\Data;

final readonly class NotificationPayload
{
    public function __construct(
        public string $keywordName,
        public string $authorName,
        public string $authorUsername,
        public string $contentSummary,
        public string $threadsUrl,
        public int $viewsCount,
        public int $likesCount,
        public int $repliesCount,
        public int $repostsCount,
        public int $quotesCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'keyword_name' => $this->keywordName,
            'author_name' => $this->authorName,
            'author_username' => $this->authorUsername,
            'content_summary' => $this->contentSummary,
            'threads_url' => $this->threadsUrl,
            'views_count' => $this->viewsCount,
            'likes_count' => $this->likesCount,
            'replies_count' => $this->repliesCount,
            'reposts_count' => $this->repostsCount,
            'quotes_count' => $this->quotesCount,
        ];
    }
}
