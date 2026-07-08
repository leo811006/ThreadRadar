<?php

namespace App\Providers\SearchProviders;

use App\Contracts\SearchProviderInterface;
use App\Data\PostData;
use App\Data\SearchQuery;
use App\Exceptions\QuotaExceededException;

/**
 * 測試與本機開發用的假資料來源，不呼叫任何外部服務。
 * 透過 setResults() 注入固定回傳值，讓 Service/Job 邏輯可在沒有 Threads API 憑證的情況下驗證。
 */
class FakeSearchProvider implements SearchProviderInterface
{
    /** @var PostData[] */
    private array $results = [];

    private ?int $quota = null;

    private bool $quotaExceeded = false;

    /**
     * @param  PostData[]  $results
     */
    public function setResults(array $results): void
    {
        $this->results = $results;
    }

    public function setRemainingQuota(?int $quota): void
    {
        $this->quota = $quota;
    }

    /**
     * 讓下一次 search() 呼叫拋出 QuotaExceededException，模擬 ThreadsApiSearchProvider
     * 在 Redis 端原子判斷配額已用盡時的行為。
     */
    public function setQuotaExceeded(bool $exceeded = true): void
    {
        $this->quotaExceeded = $exceeded;
    }

    public function search(SearchQuery $query): array
    {
        if ($this->quotaExceeded) {
            throw new QuotaExceededException('Fake quota exceeded for testing.');
        }

        return $this->results;
    }

    public function remainingQuota(): ?int
    {
        return $this->quota;
    }
}
