<?php

namespace App\Console\Commands;

use App\Models\DailyStatistic;
use App\Services\DashboardService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

/**
 * 將指定日期（預設昨天）的巡檢／文章／通知數量結算進 daily_statistics，
 * 供 FR-7 趨勢圖讀取。排程於每日凌晨跑前一天，確保結算時當天資料已完整落地；
 * 也支援 --date= 手動補跑任意一天（例如資料回補、系統中斷後補寫）。
 */
class AggregateDailyStatisticsCommand extends Command
{
    protected $signature = 'app:aggregate-daily-statistics {--date= : 要結算的日期（Y-m-d），預設為昨天}';

    protected $description = '結算指定日期的每日統計（新增文章數、更新文章數、通知次數、搜尋次數）';

    public function handle(DashboardService $dashboard): int
    {
        $dateOption = $this->option('date');

        if ($dateOption !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOption)) {
            $this->error("Invalid --date format: '{$dateOption}'. Expected Y-m-d (e.g. 2026-07-08).");

            return self::FAILURE;
        }

        $date = $dateOption
            ? Date::parse($dateOption)->startOfDay()
            : Date::yesterday()->startOfDay();

        $attributes = [
            'search_count' => $dashboard->searchCountForDate($date),
            'new_posts_count' => $dashboard->newPostsCountForDate($date),
            'updated_posts_count' => $dashboard->updatedPostsCountForDate($date),
            'notification_count' => $dashboard->notificationCountForDate($date),
        ];

        // 用 upsert() 編譯成單一原子 SQL 陳述式（MySQL: INSERT ... ON DUPLICATE KEY
        // UPDATE），而非 updateOrCreate 的 read-then-write（firstOrNew + save）：
        // 排程與手動 --date= 補跑若針對同一天同時執行，upsert 不會有 TOCTOU 競態。
        DailyStatistic::upsert(
            [['date' => $date->toDateString(), ...$attributes]],
            uniqueBy: ['date'],
            update: array_keys($attributes),
        );

        $this->info("Aggregated daily statistics for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
