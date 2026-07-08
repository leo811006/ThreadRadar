<?php

namespace App\Providers\SearchProviders;

use App\Contracts\SearchProviderInterface;
use App\Data\PostData;
use App\Data\SearchQuery;

/**
 * 測試與本機開發用的假資料來源，不呼叫任何外部服務。
 * 透過 setResults() 注入固定回傳值，讓 Service/Job 邏輯可在沒有 Threads API 憑證的情況下驗證。
 */
class FakeSearchProvider implements SearchProviderInterface
{
    /** @var PostData[] */
    private array $results = [];

    private ?int $quota = null;

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

    public function search(SearchQuery $query): array
    {
        return $this->results;
    }

    public function remainingQuota(): ?int
    {
        return $this->quota;
    }
}
