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
use App\Providers\SearchProviders\ThreadsScraperSearchProvider;
use App\Services\NotificationService;
use App\Support\Parsers\ThreadsScraperPostParser;
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
        // 非官方 headless browser 爬蟲，暫代因 threads_keyword_search 權限尚未通過
        // App Review 而無法使用的 ThreadsApiSearchProvider（個人非商業用途裁決，見 config/threads.php）。
        $this->app->bind(SearchProviderInterface::class, function ($app) {
            return new ThreadsScraperSearchProvider(
                parser: $app->make(ThreadsScraperPostParser::class),
                nodeBinary: (string) config('threads.scraper.node_binary'),
                scriptPath: (string) config('threads.scraper.script_path'),
                timeoutSeconds: (int) config('threads.scraper.timeout_seconds'),
                lockTtlSeconds: (int) config('threads.scraper.lock_ttl_seconds'),
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
    }
}
