<?php

use App\Contracts\AiAnalysisProviderInterface;
use App\Data\AiAnalysisResult;
use App\Jobs\AnalyzePostJob;
use App\Models\Post;
use App\Providers\AiAnalysisProviders\FakeAiAnalysisProvider;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;

function bindFakeAiProvider(): FakeAiAnalysisProvider
{
    $fake = new FakeAiAnalysisProvider();
    app()->instance(AiAnalysisProviderInterface::class, $fake);

    return $fake;
}

it('writes ai_summary, ai_sentiment, and ai_tags from the provider result', function () {
    $fake = bindFakeAiProvider();
    $fake->setResult(new AiAnalysisResult(
        summary: '這篇文章討論新品發表',
        sentiment: 'positive',
        tags: ['科技', '新品'],
    ));

    $post = Post::factory()->create(['ai_summary' => null]);

    (new AnalyzePostJob($post->id))->handle(app(AiAnalysisProviderInterface::class));

    $post->refresh();

    expect($post->ai_summary)->toBe('這篇文章討論新品發表')
        ->and($post->ai_sentiment)->toBe('positive')
        ->and($post->ai_tags)->toBe(['科技', '新品']);
});

it('has 3 tries configured for retrying on transient provider failures', function () {
    $post = Post::factory()->create();

    $job = new AnalyzePostJob($post->id);

    expect($job->tries)->toBe(3);
});

it('applies both WithoutOverlapping and RateLimited(gemini-ai-analysis) middleware', function () {
    $post = Post::factory()->create();

    $middleware = (new AnalyzePostJob($post->id))->middleware();

    expect($middleware)->toHaveCount(2)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[1])->toBeInstanceOf(RateLimited::class);
});

it('propagates the provider exception so the queue can retry', function () {
    $fake = bindFakeAiProvider();
    $fake->setShouldThrow();

    $post = Post::factory()->create(['ai_summary' => null]);

    expect(fn () => (new AnalyzePostJob($post->id))->handle(app(AiAnalysisProviderInterface::class)))
        ->toThrow(RuntimeException::class);

    expect($post->fresh()->ai_summary)->toBeNull();
});

it('does not throw and does not retry when the provider fails permanently', function () {
    $fake = bindFakeAiProvider();
    $fake->setShouldThrowPermanently();

    $post = Post::factory()->create(['ai_summary' => null]);

    // 不應拋出例外：AiAnalysisPermanentFailureException 應被內部捕捉並記錄，
    // 而非讓佇列繼續重試（與 CrawlKeywordJob 對配額用盡的處理一致）。
    (new AnalyzePostJob($post->id))->handle(app(AiAnalysisProviderInterface::class));

    expect($post->fresh()->ai_summary)->toBeNull();
});

it('records ai_analysis_failed_at and failure_reason when the provider fails permanently', function () {
    $fake = bindFakeAiProvider();
    $fake->setShouldThrowPermanently();

    $post = Post::factory()->create(['ai_summary' => null]);

    (new AnalyzePostJob($post->id))->handle(app(AiAnalysisProviderInterface::class));

    $fresh = $post->fresh();

    expect($fresh->ai_analysis_failed_at)->not->toBeNull()
        ->and($fresh->ai_analysis_failure_reason)->toBe('Fake permanent AI analysis failure for testing.');
});

it('skips gracefully when the post no longer exists', function () {
    bindFakeAiProvider();

    $nonExistentPostId = 999999;

    // 不應拋出 ModelNotFoundException：文章已被刪除是永久性狀況，
    // 直接記錄並跳過即可，避免浪費 3 次重試在不可能成功的工作上。
    (new AnalyzePostJob($nonExistentPostId))->handle(app(AiAnalysisProviderInterface::class));

    expect(true)->toBeTrue();
});

it('skips analysis when ai_summary is already set (duplicate dispatch guard)', function () {
    $fake = bindFakeAiProvider();
    $fake->setResult(new AiAnalysisResult(summary: 'should not be used', sentiment: 'positive', tags: ['x']));

    $post = Post::factory()->create(['ai_summary' => '已經分析過了']);

    (new AnalyzePostJob($post->id))->handle(app(AiAnalysisProviderInterface::class));

    // 保留原本的值，證明沒有重新呼叫 AI 服務覆蓋掉既有分析結果。
    expect($post->fresh()->ai_summary)->toBe('已經分析過了');
});

it('skips analysis when the post already permanently failed before (duplicate dispatch guard)', function () {
    $fake = bindFakeAiProvider();
    $fake->setResult(new AiAnalysisResult(summary: 'should not be used', sentiment: 'positive', tags: ['x']));

    $post = Post::factory()->create([
        'ai_summary' => null,
        'ai_analysis_failed_at' => now(),
        'ai_analysis_failure_reason' => '之前已永久失敗',
    ]);

    (new AnalyzePostJob($post->id))->handle(app(AiAnalysisProviderInterface::class));

    // 不應重新呼叫 AI 服務：曾經永久失敗的文章不該無止盡地重試分析。
    expect($post->fresh()->ai_summary)->toBeNull();
});

it('clears any prior failure record when a later analysis attempt succeeds', function () {
    $fake = bindFakeAiProvider();
    $fake->setResult(new AiAnalysisResult(summary: '這次成功了', sentiment: 'neutral', tags: ['x']));

    // 手動重置失敗記錄後重跑分析（例如客服判斷應該重試），驗證成功寫入時
    // 會一併清除舊的失敗記錄，避免 UI 永遠顯示失敗狀態。
    $post = Post::factory()->create([
        'ai_summary' => null,
        'ai_analysis_failed_at' => null,
        'ai_analysis_failure_reason' => null,
    ]);

    (new AnalyzePostJob($post->id))->handle(app(AiAnalysisProviderInterface::class));

    $fresh = $post->fresh();

    expect($fresh->ai_summary)->toBe('這次成功了')
        ->and($fresh->ai_analysis_failed_at)->toBeNull()
        ->and($fresh->ai_analysis_failure_reason)->toBeNull();
});
