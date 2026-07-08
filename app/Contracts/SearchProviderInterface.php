<?php

namespace App\Contracts;

use App\Data\PostData;
use App\Data\SearchQuery;
use App\Exceptions\QuotaExceededException;

interface SearchProviderInterface
{
    /**
     * 配額檢查與保留須在實作內部以原子操作完成（例如呼叫前一併遞增計數器），
     * 不可只依賴呼叫端事先呼叫 remainingQuota() 判斷——那只是盡力而為的預先提示，
     * 在併發呼叫下會有 check-then-act 競態，無法保證不超額。
     *
     * @return PostData[]
     *
     * @throws QuotaExceededException 配額已用盡
     */
    public function search(SearchQuery $query): array;

    /**
     * 回傳今日剩餘可用查詢配額（僅供參考的預估值，非配額保留的權威判斷）；
     * 若該資料來源沒有配額限制、或無法確認目前配額狀態（如底層儲存暫時無法連線），回傳 null。
     */
    public function remainingQuota(): ?int;
}
