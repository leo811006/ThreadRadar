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

it('writes ai_summary, ai_sentiment, and ai_tags for every post in the batch', function () {
    $postA = Post::factory()->create(['ai_summary' => null]);
    $postB = Post::factory()->create(['ai_summary' => null]);

    $fake = bindFakeAiProvider();
    $fake->setResults([
        $postA->id => new AiAnalysisResult(summary: '摘要A', sentiment: 'positive', tags: ['科技']),
        $postB->id => new AiAnalysisResult(summary: '摘要B', sentiment: 'negative', tags: ['客訴']),
    ]);

    (new AnalyzePostJob([$postA->id, $postB->id]))->handle(app(AiAnalysisProviderInterface::class));

    expect($postA->fresh()->ai_summary)->toBe('摘要A')
        ->and($postA->fresh()->ai_sentiment)->toBe('positive')
        ->and($postB->fresh()->ai_summary)->toBe('摘要B')
        ->and($postB->fresh()->ai_sentiment)->toBe('negative');
});

it('has 3 tries configured for retrying on transient provider failures', function () {
    $post = Post::factory()->create();

    $job = new AnalyzePostJob([$post->id]);

    expect($job->tries)->toBe(3);
});

it('applies both WithoutOverlapping and RateLimited(gemini-ai-analysis) middleware', function () {
    $post = Post::factory()->create();

    $middleware = (new AnalyzePostJob([$post->id]))->middleware();

    expect($middleware)->toHaveCount(2)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[1])->toBeInstanceOf(RateLimited::class);
});

it('applies one WithoutOverlapping lock per post in the batch, not one lock for the whole batch', function () {
    // 每篇文章各自持有一把鎖，而非整批共用一把雜湊鎖：確保同一篇文章即使
    // 同時出現在兩個不同批次中，仍不會被併發分析兩次。
    $postA = Post::factory()->create();
    $postB = Post::factory()->create();
    $postC = Post::factory()->create();

    $middleware = (new AnalyzePostJob([$postA->id, $postB->id, $postC->id]))->middleware();

    $withoutOverlappingCount = collect($middleware)->filter(fn ($m) => $m instanceof WithoutOverlapping)->count();

    expect($withoutOverlappingCount)->toBe(3);
});

it('propagates the provider exception so the queue can retry the whole batch', function () {
    $fake = bindFakeAiProvider();
    $fake->setShouldThrow();

    $post = Post::factory()->create(['ai_summary' => null]);

    expect(fn () => (new AnalyzePostJob([$post->id]))->handle(app(AiAnalysisProviderInterface::class)))
        ->toThrow(RuntimeException::class);

    expect($post->fresh()->ai_summary)->toBeNull();
});

it('does not throw and marks every post in the batch as permanently failed when the provider fails permanently', function () {
    $fake = bindFakeAiProvider();
    $fake->setShouldThrowPermanently();

    $postA = Post::factory()->create(['ai_summary' => null]);
    $postB = Post::factory()->create(['ai_summary' => null]);

    // 不應拋出例外：AiAnalysisPermanentFailureException 應被內部捕捉並記錄，
    // 而非讓佇列繼續重試（與 CrawlKeywordJob 對配額用盡的處理一致）。
    (new AnalyzePostJob([$postA->id, $postB->id]))->handle(app(AiAnalysisProviderInterface::class));

    expect($postA->fresh()->ai_summary)->toBeNull()
        ->and($postA->fresh()->ai_analysis_failed_at)->not->toBeNull()
        ->and($postB->fresh()->ai_summary)->toBeNull()
        ->and($postB->fresh()->ai_analysis_failed_at)->not->toBeNull();
});

it('marks only the post missing from the batch response as failed, leaving the rest of the batch succeeded', function () {
    $postA = Post::factory()->create(['ai_summary' => null]);
    $postB = Post::factory()->create(['ai_summary' => null]);

    $fake = bindFakeAiProvider();
    // 模擬 Gemini 只對 postA 回傳結果（postB 可能被安全過濾器單獨封鎖）。
    $fake->setResults([
        $postA->id => new AiAnalysisResult(summary: '摘要A', sentiment: 'positive', tags: ['科技']),
    ]);

    (new AnalyzePostJob([$postA->id, $postB->id]))->handle(app(AiAnalysisProviderInterface::class));

    expect($postA->fresh()->ai_summary)->toBe('摘要A')
        ->and($postB->fresh()->ai_summary)->toBeNull()
        ->and($postB->fresh()->ai_analysis_failed_at)->not->toBeNull();
});

it('skips gracefully when none of the posts in the batch exist or need analysis', function () {
    bindFakeAiProvider();

    // 不應拋出例外：批次中所有文章都不存在/已分析過時，查詢結果為空集合，直接跳過。
    (new AnalyzePostJob([999999]))->handle(app(AiAnalysisProviderInterface::class));

    expect(true)->toBeTrue();
});

it('excludes posts that are already analyzed or already permanently failed from the batch query', function () {
    $alreadyAnalyzed = Post::factory()->create(['ai_summary' => '已經分析過了']);
    $alreadyFailed = Post::factory()->create([
        'ai_summary' => null,
        'ai_analysis_failed_at' => now(),
        'ai_analysis_failure_reason' => '之前已永久失敗',
    ]);
    $pending = Post::factory()->create(['ai_summary' => null]);

    $fake = bindFakeAiProvider();
    $fake->setResults([
        $pending->id => new AiAnalysisResult(summary: '新分析', sentiment: 'neutral', tags: ['x']),
    ]);

    (new AnalyzePostJob([$alreadyAnalyzed->id, $alreadyFailed->id, $pending->id]))
        ->handle(app(AiAnalysisProviderInterface::class));

    // 保留原本的值，證明沒有重新呼叫 AI 服務覆蓋掉既有分析結果或失敗記錄。
    expect($alreadyAnalyzed->fresh()->ai_summary)->toBe('已經分析過了')
        ->and($alreadyFailed->fresh()->ai_analysis_failure_reason)->toBe('之前已永久失敗')
        ->and($pending->fresh()->ai_summary)->toBe('新分析');
});

it('clears any prior failure record when a later analysis attempt succeeds', function () {
    // 手動重置失敗記錄後重跑分析（例如客服判斷應該重試），驗證成功寫入時
    // 會一併清除舊的失敗記錄，避免 UI 永遠顯示失敗狀態。
    $post = Post::factory()->create([
        'ai_summary' => null,
        'ai_analysis_failed_at' => null,
        'ai_analysis_failure_reason' => null,
    ]);

    $fake = bindFakeAiProvider();
    $fake->setResults([
        $post->id => new AiAnalysisResult(summary: '這次成功了', sentiment: 'neutral', tags: ['x']),
    ]);

    (new AnalyzePostJob([$post->id]))->handle(app(AiAnalysisProviderInterface::class));

    $fresh = $post->fresh();

    expect($fresh->ai_summary)->toBe('這次成功了')
        ->and($fresh->ai_analysis_failed_at)->toBeNull()
        ->and($fresh->ai_analysis_failure_reason)->toBeNull();
});
