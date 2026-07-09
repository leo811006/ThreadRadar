<?php

namespace App\Contracts;

use App\Data\AiAnalysisResult;
use App\Models\Post;

interface AiAnalysisProviderInterface
{
    /**
     * 對單篇文章做摘要、情緒判斷、標籤建議。實作應為單次外部 API 呼叫，
     * 失敗時拋出例外交由呼叫端（AnalyzePostJob）處理重試，不在此吞錯。
     */
    public function analyze(Post $post): AiAnalysisResult;
}
