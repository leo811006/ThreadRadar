<?php

namespace App\Providers\AiAnalysisProviders;

use App\Contracts\AiAnalysisProviderInterface;
use App\Data\AiAnalysisResult;
use App\Exceptions\AiAnalysisPermanentFailureException;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * 測試與本機開發用的假資料來源，不呼叫任何外部服務。
 * 透過 setResults()/setShouldThrow()/setShouldThrowPermanently() 注入固定回傳值或例外，
 * 讓 AnalyzePostJob 的邏輯可在沒有 Gemini API 憑證的情況下驗證。
 */
class FakeAiAnalysisProvider implements AiAnalysisProviderInterface
{
    /** @var array<int, AiAnalysisResult>|null */
    private ?array $results = null;

    private bool $shouldThrow = false;

    private bool $shouldThrowPermanently = false;

    /**
     * @param  array<int, AiAnalysisResult>  $results  以 postId 為 key
     */
    public function setResults(array $results): void
    {
        $this->results = $results;
    }

    public function setShouldThrow(bool $shouldThrow = true): void
    {
        $this->shouldThrow = $shouldThrow;
    }

    /**
     * 模擬 GeminiAiAnalysisProvider 遇到安全過濾器封鎖/回應格式永久不符預期時
     * 拋出的 AiAnalysisPermanentFailureException，供測試 AnalyzePostJob 不重試的行為。
     */
    public function setShouldThrowPermanently(bool $shouldThrow = true): void
    {
        $this->shouldThrowPermanently = $shouldThrow;
    }

    public function analyzeBatch(Collection $posts): array
    {
        if ($this->shouldThrowPermanently) {
            throw new AiAnalysisPermanentFailureException('Fake permanent AI analysis failure for testing.');
        }

        if ($this->shouldThrow) {
            throw new RuntimeException('Fake AI analysis failure for testing.');
        }

        if ($this->results !== null) {
            return $this->results;
        }

        return $posts->mapWithKeys(fn ($post) => [
            $post->id => new AiAnalysisResult(
                summary: 'fake summary',
                sentiment: 'neutral',
                tags: ['fake-tag'],
            ),
        ])->all();
    }
}
