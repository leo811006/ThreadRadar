<?php

namespace App\Providers\SearchProviders;

use App\Contracts\SearchProviderInterface;
use App\Data\PostData;
use App\Data\SearchQuery;
use App\Exceptions\QuotaExceededException;
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

    /**
     * 「INCR 後檢查上限、超過則退回」的 Lua script，讓遞增與配額檢查在 Redis 端以單一
     * 原子操作完成，避免多 queue worker 併發時的 check-then-act 競態（先讀 remainingQuota()
     * 判斷足夠、再各自呼叫 API 遞增，可能同時讀到「足夠」而一起放行、實際超額）。
     * 同一個 EVAL 呼叫內設定 TTL，避免 INCR 與 EXPIRE 分成兩個指令、中間中斷導致 key 無 TTL 永久殘留。
     *
     * KEYS[1] = quota key, ARGV[1] = daily quota, ARGV[2] = TTL seconds
     * 回傳值：遞增後仍在配額內則回傳新的使用量；已超額則回傳 -1（不遞增）。
     */
    private const INCR_WITH_LIMIT_SCRIPT = <<<'LUA'
        local used = tonumber(redis.call('GET', KEYS[1]) or '0')
        local limit = tonumber(ARGV[1])
        if used >= limit then
            return -1
        end
        local new_used = redis.call('INCR', KEYS[1])
        redis.call('EXPIRE', KEYS[1], ARGV[2])
        return new_used
        LUA;

    public function __construct(
        private readonly ThreadsPostParser $parser,
        private readonly string $accessToken,
        private readonly int $dailyQuota,
    ) {}

    /**
     * @throws QuotaExceededException 配額已用盡（Redis 端原子判斷，避免併發 worker 超額扣打）
     */
    public function search(SearchQuery $query): array
    {
        $this->reserveQuotaOrFail();

        $response = Http::withToken($this->accessToken)
            ->get(self::API_BASE_URL . '/keyword_search', [
                'q' => $query->keyword,
                'since' => $query->since->timestamp,
                'until' => $query->until?->timestamp,
                'fields' => 'permalink,username,timestamp,text,views,likes,replies,reposts,quotes,is_verified,media_attachments',
            ])
            ->throw();

        return collect($response->json('data', []))
            ->map(fn (array $raw) => $this->parser->parse($raw))
            ->all();
    }

    /**
     * 供呼叫端（如 CrawlKeywordJob）在真正發起查詢前做粗略的預先判斷用；真正的配額保護
     * 在 search() 內部以原子操作執行，這裡的讀取本身仍可能有些微延遲，不是配額扣打的權威來源。
     * Redis 連線失敗時回傳 null（視為配額狀態未知），呼叫端應將其視為「無法確認」而非「配額用盡」。
     */
    public function remainingQuota(): ?int
    {
        try {
            $used = (int) Redis::get($this->todayQuotaKey());
        } catch (\Throwable) {
            return null;
        }

        return max(0, $this->dailyQuota - $used);
    }

    /**
     * @throws QuotaExceededException
     */
    private function reserveQuotaOrFail(): void
    {
        $result = (int) Redis::eval(
            self::INCR_WITH_LIMIT_SCRIPT,
            1,
            $this->todayQuotaKey(),
            $this->dailyQuota,
            86400,
        );

        if ($result === -1) {
            throw new QuotaExceededException('Threads API daily quota exceeded.');
        }
    }

    private function todayQuotaKey(): string
    {
        return self::QUOTA_CACHE_KEY . now()->utc()->format('Y-m-d');
    }
}
