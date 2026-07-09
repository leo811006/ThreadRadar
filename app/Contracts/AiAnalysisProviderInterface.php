<?php

namespace App\Contracts;

use App\Data\AiAnalysisResult;
use Illuminate\Support\Collection;

interface AiAnalysisProviderInterface
{
    /**
     * 對一批文章做摘要、情緒判斷、標籤建議，實作應以單次外部 API 呼叫處理整批，
     * 而非逐篇個別呼叫，藉此降低 API 請求次數（RPM/RPD 額度）。呼叫端需自行
     * 控制批次大小以避免超過單次請求的 token 上限（TPM 額度）。
     *
     * 失敗時拋出例外交由呼叫端（AnalyzePostJob）處理重試，不在此吞錯；
     * 例外會讓整批失敗（無法呼叫 API 時無法區分哪幾篇有問題）。
     *
     * @param  Collection<int, \App\Models\Post>  $posts
     * @return array<int, AiAnalysisResult> 以 Post::$id 為 key，只包含成功分析的項目；
     *                                       若模型判斷某篇無法分析（如安全過濾器封鎖單篇），
     *                                       該 postId 可以不出現在回傳陣列中。
     */
    public function analyzeBatch(Collection $posts): array;
}
