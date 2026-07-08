<?php

namespace App\Contracts;

use App\Data\PostData;
use App\Data\SearchQuery;

interface SearchProviderInterface
{
    /**
     * @return PostData[]
     */
    public function search(SearchQuery $query): array;

    /**
     * 回傳今日剩餘可用查詢配額；若該資料來源沒有配額限制則回傳 null。
     */
    public function remainingQuota(): ?int;
}
