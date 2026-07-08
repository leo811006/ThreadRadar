<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class PostData
{
    /**
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
        public int $viewsCount,
        public int $likesCount,
        public int $repliesCount,
        public int $repostsCount,
        public int $quotesCount,
        public bool $isVerifiedAuthor,
    ) {}
}
