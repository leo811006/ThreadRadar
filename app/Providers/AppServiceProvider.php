<?php

namespace App\Providers;

use App\Contracts\AiAnalysisProviderInterface;
use App\Contracts\SearchProviderInterface;
use App\Notifications\Channels\DiscordWebhookChannel;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\GenericWebhookChannel;
use App\Notifications\Channels\LineMessagingChannel;
use App\Notifications\Channels\SlackWebhookChannel;
use App\Notifications\Channels\TelegramBotChannel;
use App\Providers\AiAnalysisProviders\GeminiAiAnalysisProvider;
use App\Providers\SearchProviders\ThreadsApiSearchProvider;
use App\Services\NotificationService;
use App\Support\Parsers\ThreadsPostParser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SearchProviderInterface::class, function ($app) {
            return new ThreadsApiSearchProvider(
                parser: $app->make(ThreadsPostParser::class),
                accessToken: (string) config('threads.access_token'),
                dailyQuota: (int) config('threads.daily_quota'),
            );
        });

        $this->app->bind(AiAnalysisProviderInterface::class, function ($app) {
            return new GeminiAiAnalysisProvider(
                apiKey: (string) config('gemini.api_key'),
                model: (string) config('gemini.model'),
            );
        });

        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService([
                'email' => $app->make(EmailChannel::class),
                'discord' => $app->make(DiscordWebhookChannel::class),
                'slack' => $app->make(SlackWebhookChannel::class),
                'line' => $app->make(LineMessagingChannel::class),
                'telegram' => $app->make(TelegramBotChannel::class),
                'webhook' => $app->make(GenericWebhookChannel::class),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Gemini 免費層級限制每分鐘 15 次請求（RPM），超過會收到 429。
        // 以 job 層級的 rate limiter 主動節流，避免 AnalyzePostJob 在短時間內
        // 大量 dispatch 時（例如一次巡檢命中多篇達標文章）直接把額度打爆。
        RateLimiter::for('gemini-ai-analysis', function () {
            return Limit::perMinute(15);
        });

        // Threads API 同樣限制每分鐘 15 次請求：多組關鍵字若排程時間重疊
        // （例如同一批次建立、間隔設定相同），DispatchDueCrawlsCommand
        // 可能在同一分鐘內一次 dispatch 大量 CrawlKeywordJob，需主動節流。
        RateLimiter::for('threads-api', function () {
            return Limit::perMinute(15);
        });
    }
}
