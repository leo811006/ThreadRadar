<?php

namespace App\Console\Commands;

use App\Jobs\CrawlKeywordJob;
use App\Models\Keyword;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

/**
 * 每分鐘由 Scheduler 觸發：找出所有到期的啟用關鍵字並 dispatch CrawlKeywordJob。
 * 「到期」定義：is_active=true 且 (從未巡檢過，或 last_crawled_at + crawl_interval_min 分鐘 已過)。
 * 以 PHP 端逐筆比較而非 DB 端日期運算，避免 MySQL/SQLite 語法不相容（docs/05-database-schema.md 測試環境為 SQLite）。
 */
class DispatchDueCrawlsCommand extends Command
{
    protected $signature = 'app:dispatch-due-crawls';

    protected $description = '找出到期的啟用關鍵字並 dispatch 巡檢 Job';

    public function handle(): int
    {
        $now = Date::now();
        $dispatched = 0;

        Keyword::query()
            ->where('is_active', true)
            ->each(function (Keyword $keyword) use ($now, &$dispatched) {
                if ($this->isDue($keyword, $now)) {
                    CrawlKeywordJob::dispatch($keyword->id)->onQueue('crawl');
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} crawl job(s).");

        return self::SUCCESS;
    }

    private function isDue(Keyword $keyword, CarbonInterface $now): bool
    {
        if ($keyword->last_crawled_at === null) {
            return true;
        }

        return $keyword->last_crawled_at
            ->addMinutes($keyword->crawl_interval_min)
            ->lessThanOrEqualTo($now);
    }
}
