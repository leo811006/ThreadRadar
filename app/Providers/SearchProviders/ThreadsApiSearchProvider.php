<?php

namespace App\Providers\SearchProviders;

use App\Contracts\SearchProviderInterface;
use App\Data\PostData;
use App\Data\SearchQuery;
use App\Support\Parsers\ThreadsPostParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

/**
 * Threads 官方 Keyword Search API 整合（docs/02-threads-data-source-feasibility.md）。
 * 配額：每使用者/日 2,200 次查詢，經 Redis 計數器追蹤，於 UTC 午夜隨 Meta 配額重置時間一併歸零。
 */
class ThreadsApiSearchProvider implements SearchProviderInterface
{
    private const API_BASE_URL = 'https://graph.threads.net/v1.0';

    private const QUOTA_CACHE_KEY = 'threads_api:quota_used:';

    public function __construct(
        private readonly ThreadsPostParser $parser,
        private readonly string $accessToken,
        private readonly int $dailyQuota,
    ) {}

    public function search(SearchQuery $query): array
    {
        $response = Http::withToken($this->accessToken)
            ->get(self::API_BASE_URL . '/keyword_search', [
                'q' => $query->keyword,
                'since' => $query->since->timestamp,
                'until' => $query->until?->timestamp,
                'fields' => 'permalink,username,timestamp,text,views,likes,replies,reposts,quotes,is_verified,media_attachments',
            ])
            ->throw();

        $this->incrementQuotaUsage();

        return collect($response->json('data', []))
            ->map(fn (array $raw) => $this->parser->parse($raw))
            ->all();
    }

    public function remainingQuota(): ?int
    {
        $used = (int) Redis::get($this->todayQuotaKey());

        return max(0, $this->dailyQuota - $used);
    }

    private function incrementQuotaUsage(): void
    {
        $key = $this->todayQuotaKey();

        Redis::incr($key);
        Redis::expire($key, 86400);
    }

    private function todayQuotaKey(): string
    {
        return self::QUOTA_CACHE_KEY . now()->utc()->format('Y-m-d');
    }
}
