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
 * 對已達標的文章做批次 AI 分析（FR-8）：摘要、情緒、標籤。一次 job 打包一批
 * 文章、以單次 Gemini API 呼叫處理整批，降低 API 請求次數以遵守 RPM/RPD 額度限制。
 * 只對達標文章分析，避免對所有巡檢到的文章都呼叫外部 AI 服務造成不可控成本。
 */
class AnalyzePostJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    /**
     * @param  array<int, int>  $postIds
     */
    public function __construct(
        public readonly array $postIds,
    ) {
        $this->onQueue('ai-analysis');
    }

    /**
     * RateLimited('gemini-ai-analysis')：Gemini 免費層級每分鐘最多 15 次請求，
     * 超過限制的 job 會自動 release 回佇列延後執行，而非直接打 API 換來 429。
     *
     * 每篇文章各自持有一把 WithoutOverlapping 鎖（而非整批共用一把雜湊鎖）：
     * 同一篇文章可能同時出現在兩個不同批次中（例如兩次巡檢時間相近、各自
     * 組出不同批次但都命中同一篇文章），若鎖是綁在「整批內容」上，兩個
     * 內容不同的批次會被視為互不相干而同時執行，導致同一篇文章被併發分析
     * 兩次。改成每篇文章各自加鎖，才能還原單篇版本原有的併發保護。
     *
     * @return array<int, WithoutOverlapping|RateLimited>
     */
    public function middleware(): array
    {
        return [
            ...array_map(fn (int $postId) => new WithoutOverlapping((string) $postId), $this->postIds),
            new RateLimited('gemini-ai-analysis'),
        ];
    }

    public function handle(AiAnalysisProviderInterface $aiAnalysisProvider): void
    {
        $posts = Post::query()
            ->whereIn('id', $this->postIds)
            ->whereNull('ai_summary')
            ->whereNull('ai_analysis_failed_at')
            ->get();

        // 批次中每篇文章各自檢查是否已分析過/已永久失敗過（而非整批跳過），
        // 因為同一批可能混雜「已被其他重複 dispatch 處理過」與「尚待分析」的文章。
        // 記錄哪些 postId 被篩掉（不存在，或已被併發的另一批處理過），維持與
        // 單篇版本相同的可觀測性，避免這類情況完全無跡可循。
        $skippedPostIds = array_diff($this->postIds, $posts->pluck('id')->all());

        if (! empty($skippedPostIds)) {
            Log::info('AnalyzePostJob skipped posts already handled or no longer existing: ['.implode(',', $skippedPostIds).'].');
        }

        if ($posts->isEmpty()) {
            return;
        }

        try {
            $results = $aiAnalysisProvider->analyzeBatch($posts);
        } catch (AiAnalysisPermanentFailureException $e) {
            // 整批請求本身永久失敗（安全過濾器封鎖、回應格式永久不符預期），
            // 記錄後放棄，不佔用重試次數，且批次中每篇文章都標記為失敗。
            Log::warning('AnalyzePostJob batch permanently failed for posts ['.$posts->pluck('id')->implode(',')."]: {$e->getMessage()}");

            $this->markPermanentlyFailed($posts->pluck('id')->all(), $e->getMessage());

            return;
        }

        $missingPostIds = [];

        foreach ($posts as $post) {
            $result = $results[$post->id] ?? null;

            if ($result === null) {
                $missingPostIds[] = $post->id;

                continue;
            }

            $post->update([
                'ai_summary' => $result->summary,
                'ai_sentiment' => $result->sentiment,
                'ai_tags' => $result->tags,
                // 保險起見一併清空失敗記錄：正常情況下能走到這裡代表上面的查詢條件
                // 已排除「先前永久失敗過」的文章，但明確清空能避免任何手動重置後
                // 重跑的邊界情況讓 UI 卡在「失敗」狀態。
                'ai_analysis_failed_at' => null,
                'ai_analysis_failure_reason' => null,
            ]);
        }

        if (! empty($missingPostIds)) {
            // 模型未對這幾篇文章回傳結果（例如該篇內容被安全過濾器單獨判定違規，
            // 但同批其他篇正常），視為永久失敗，不影響同批其他篇的結果。
            Log::warning('AnalyzePostJob: posts missing from Gemini batch response: ['.implode(',', $missingPostIds).'].');

            $this->markPermanentlyFailed(
                $missingPostIds,
                'Gemini 未對此篇文章回傳分析結果（可能被安全過濾器單獨封鎖）。',
            );
        }
    }

    /**
     * @param  array<int, int>  $postIds
     */
    private function markPermanentlyFailed(array $postIds, string $reason): void
    {
        Post::whereIn('id', $postIds)->update([
            'ai_analysis_failed_at' => now(),
            'ai_analysis_failure_reason' => $reason,
        ]);
    }
}
