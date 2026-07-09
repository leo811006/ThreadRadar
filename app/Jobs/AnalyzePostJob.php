<?php

namespace App\Jobs;

use App\Contracts\AiAnalysisProviderInterface;
use App\Exceptions\AiAnalysisPermanentFailureException;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * 對已達標的文章做 AI 分析（FR-8）：摘要、情緒、標籤。只對達標文章分析，
 * 避免對所有巡檢到的文章都呼叫外部 AI 服務造成不可控成本。
 */
class AnalyzePostJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $postId,
    ) {
        $this->onQueue('ai-analysis');
    }

    /**
     * 以 postId 為鍵防止同一篇文章的 AnalyzePostJob 併發執行：同一篇文章命中
     * 多個關鍵字時，CrawlKeywordJob 可能各自 dispatch 一次分析工作，
     * 此中介層確保實際只會有一個在跑，另一個等待後會因下方 ai_summary 已寫入而跳過。
     *
     * RateLimited('gemini-ai-analysis')：Gemini 免費層級每分鐘最多 15 次請求，
     * 超過限制的 job 會自動 release 回佇列延後執行，而非直接打 API 換來 429。
     *
     * @return array<int, WithoutOverlapping|RateLimited>
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping((string) $this->postId),
            new RateLimited('gemini-ai-analysis'),
        ];
    }

    public function handle(AiAnalysisProviderInterface $aiAnalysisProvider): void
    {
        $post = Post::find($this->postId);

        if ($post === null) {
            // 文章已被刪除，這是永久性狀況，重試也不會有結果，直接放棄不拋例外。
            Log::warning("AnalyzePostJob skipped: post #{$this->postId} no longer exists.");

            return;
        }

        if ($post->ai_summary !== null || $post->ai_analysis_failed_at !== null) {
            // 與 WithoutOverlapping 中介層搭配：另一個重複 dispatch 的 job 已經完成分析
            // 或已經永久失敗過，這裡直接跳過，避免對同一篇文章重複呼叫 AI 服務造成
            // 無止盡的額外成本（例如同一篇文章又命中新的關鍵字，導致重新 dispatch）。
            return;
        }

        try {
            $result = $aiAnalysisProvider->analyze($post);
        } catch (AiAnalysisPermanentFailureException $e) {
            // 安全過濾器封鎖、回應格式永久不符預期等狀況重試也不會成功，
            // 記錄後放棄，不佔用重試次數（與 CrawlKeywordJob 對配額用盡的處理一致）。
            // 同時落地寫入 ai_analysis_failed_at/reason，供後台統計失敗率使用。
            Log::warning("AnalyzePostJob permanently failed for post #{$post->id}: {$e->getMessage()}");

            $post->update([
                'ai_analysis_failed_at' => now(),
                'ai_analysis_failure_reason' => $e->getMessage(),
            ]);

            return;
        }

        $post->update([
            'ai_summary' => $result->summary,
            'ai_sentiment' => $result->sentiment,
            'ai_tags' => $result->tags,
            // 保險起見一併清空失敗記錄：正常情況下能走到這裡代表上面的 skip guard
            // 已排除「先前永久失敗過」的文章，但明確清空能避免任何手動重置後
            // 重跑的邊界情況讓 UI 卡在「失敗」狀態。
            'ai_analysis_failed_at' => null,
            'ai_analysis_failure_reason' => null,
        ]);
    }
}
