<?php

namespace App\Console\Commands;

use App\Jobs\CrawlKeywordJob;
use App\Models\Keyword;
use Illuminate\Console\Command;

/**
 * 手動一鍵巡檢：忽略 crawl_interval_min/last_crawled_at 到期判斷，
 * 強制對所有 is_active=true 的關鍵字立即 dispatch 一次 CrawlKeywordJob。
 * 用於本機測試/驗證憑證是否生效，不受 Scheduler 排程限制。
 */
class CrawlAllNowCommand extends Command
{
    protected $signature = 'app:crawl-all-now {--sync : 不進佇列，同步立即執行並直接顯示結果，不需要 queue worker}';

    protected $description = '忽略排程，立即巡檢所有啟用中的關鍵字（供手動測試使用）';

    public function handle(): int
    {
        $keywords = Keyword::query()->where('is_active', true)->get();

        if ($keywords->isEmpty()) {
            $this->warn('沒有任何啟用中的關鍵字。');

            return self::SUCCESS;
        }

        foreach ($keywords as $keyword) {
            if ($this->option('sync')) {
                CrawlKeywordJob::dispatchSync($keyword->id);
            } else {
                CrawlKeywordJob::dispatch($keyword->id)->onQueue('crawl');
            }
        }

        $mode = $this->option('sync') ? '同步執行完成' : '已 dispatch 到佇列（需要 queue worker 執行中才會實際跑）';
        $this->info("{$keywords->count()} 個關鍵字{$mode}。");

        return self::SUCCESS;
    }
}
