<?php

namespace App\Contracts;

use App\Data\PostData;
use App\Data\SearchQuery;
use App\Exceptions\QuotaExceededException;

interface SearchProviderInterface
{
    /**
     * 若資料來源有配額限制（如官方 API 的每日查詢上限），配額檢查與保留須在實作
     * 內部以原子操作完成（例如呼叫前一併遞增計數器），不可只依賴呼叫端事先呼叫
     * remainingQuota() 判斷——那只是盡力而為的預先提示，在併發呼叫下會有
     * check-then-act 競態，無法保證不超額。
     *
     * 若資料來源沒有官方配額機制（如非官方爬蟲），仍必須在實作內部處理該來源
     * 特有的資源保護需求（例如避免多個呼叫端同時對同一目標發起大量請求），
     * 不可要求呼叫端自行協調——原則相同，只是保護對象從「配額」換成其他限制。
     *
     * @return PostData[]
     *
     * @throws QuotaExceededException 配額已用盡（僅有配額機制的來源適用）
     */
    public function search(SearchQuery $query): array;

    /**
     * 回傳今日剩餘可用查詢配額（僅供參考的預估值，非配額保留的權威判斷）。
     * 回傳 null 涵蓋兩種情況，呼叫端不應假設兩者等價：
     * 1. 該資料來源沒有配額概念（如非官方爬蟲），此為穩定狀態；
     * 2. 無法確認目前配額狀態（如底層儲存暫時無法連線），此為暫時性、應視為不確定而非安全。
     */
    public function remainingQuota(): ?int;
}
