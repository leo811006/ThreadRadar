<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class PostData
{
    /**
     * 互動數類欄位（views/likes/replies/reposts/quotes）使用 null 表示「此資料來源
     * 無法取得該指標」，與「實際數值為 0」明確區分——PostUpsertService 依此決定是否
     * 覆蓋既有紀錄，FilterService 依此決定門檻比對是否可信（見兩者內部說明）。
     *
     * @param  string[]  $images
     * @param  string[]  $videos
     */
    public function __construct(
        public string $threadsUrl,
        public string $authorName,
        public string $authorUsername,
        public CarbonImmutable $postedAt,
        public string $content,
        public array $images,
        public array $videos,
        public ?int $viewsCount,
        public ?int $likesCount,
        public ?int $repliesCount,
        public ?int $repostsCount,
        public ?int $quotesCount,
        public bool $isVerifiedAuthor,
    ) {}
}
