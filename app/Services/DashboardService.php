<?php

namespace App\Services;

use App\Models\CrawlLog;
use App\Models\DailyStatistic;
use App\Models\Keyword;
use App\Models\NotificationLog;
use App\Models\Post;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * Dashboard 統計聚合（FR-7）。今日統計短時間快取，避免每次請求都重新聚合查詢。
 */
class DashboardService
{
    private const CACHE_TTL_SECONDS = 60;

    /**
     * trends() 只讀取已結算的歷史快照（每日僅寫入一次），staleness 容忍度遠高於
     * 即時的 today*Count() 系列方法，可用長很多的 TTL 減少無意義的重複查詢。
     */
    private const TRENDS_CACHE_TTL_SECONDS = 3600;

    public function todaySearchCount(): int
    {
        return Cache::remember('dashboard:today_search_count', self::CACHE_TTL_SECONDS, fn () => $this->searchCountForDate(Date::today()));
    }

    public function todayNewPostsCount(): int
    {
        return Cache::remember('dashboard:today_new_posts_count', self::CACHE_TTL_SECONDS, fn () => $this->newPostsCountForDate(Date::today()));
    }

    public function todayUpdatedPostsCount(): int
    {
        return Cache::remember('dashboard:today_updated_posts_count', self::CACHE_TTL_SECONDS, fn () => $this->updatedPostsCountForDate(Date::today()));
    }

    public function todayNotificationCount(): int
    {
        return Cache::remember('dashboard:today_notification_count', self::CACHE_TTL_SECONDS, fn () => $this->notificationCountForDate(Date::today()));
    }

    /**
     * 供 app:aggregate-daily-statistics 結算任意一天使用，與 today*Count() 共用
     * 同一套「當天算什麼」的業務規則，避免兩處各自維護幾乎相同的查詢條件而彼此漂移。
     * 歷史日期資料已定案不需快取，故不經過 Cache::remember。
     */
    public function searchCountForDate(CarbonInterface $date): int
    {
        return CrawlLog::whereDate('started_at', $date)->count();
    }

    public function newPostsCountForDate(CarbonInterface $date): int
    {
        return Post::whereDate('first_seen_at', $date)->count();
    }

    public function updatedPostsCountForDate(CarbonInterface $date): int
    {
        return Post::whereDate('last_seen_at', $date)
            ->whereColumn('last_seen_at', '!=', 'first_seen_at')
            ->count();
    }

    public function notificationCountForDate(CarbonInterface $date): int
    {
        return NotificationLog::where('status', 'sent')
            ->whereDate('sent_at', $date)
            ->count();
    }

    /**
     * @return Collection<int, Post>
     */
    public function topPosts(int $limit = 20): Collection
    {
        return Cache::remember("dashboard:top_posts:{$limit}", self::CACHE_TTL_SECONDS, function () use ($limit) {
            return Post::query()->orderByHotness()->limit($limit)->get();
        });
    }

    /**
     * @return Collection<int, object{author_username: string, author_name: string, total_hotness: int, post_count: int}>
     */
    public function topAuthors(int $limit = 20): Collection
    {
        return Cache::remember(
            "dashboard:top_authors:{$limit}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->topAuthorsQuery($limit)->get(),
        );
    }

    /**
     * 供需要自行掌控查詢執行時機的呼叫端使用（例如 Filament TableWidget 需要一個
     * 未執行的 Builder 交給元件內部處理），與 topAuthors() 共用同一段聚合邏輯，
     * 避免兩處各自維護一份幾乎相同的 selectRaw 字串而彼此不同步。
     */
    public function topAuthorsQuery(int $limit = 20): Builder
    {
        return Post::query()
            ->selectRaw('author_username, MAX(author_name) as author_name, COUNT(*) as post_count, SUM(' . Post::HOTNESS_SCORE_EXPRESSION . ') as total_hotness')
            ->groupBy('author_username')
            ->orderByDesc('total_hotness')
            ->limit($limit);
    }

    /**
     * @return Collection<int, object{id: int, name: string, post_count: int}>
     */
    public function topKeywords(int $limit = 20): Collection
    {
        return Cache::remember("dashboard:top_keywords:{$limit}", self::CACHE_TTL_SECONDS, function () use ($limit) {
            return Keyword::query()
                ->withCount('postMatches')
                ->orderByDesc('post_matches_count')
                ->limit($limit)
                ->get(['id', 'name']);
        });
    }

    /**
     * 每日趨勢（FR-7）：讀取已由 app:aggregate-daily-statistics 結算的每日快照，
     * 並在尾端補上「今天」的即時統計（未落地寫入 daily_statistics，僅供顯示）。
     * 若沒有這一段補值，圖表最新一點永遠停在昨天，容易被誤讀為系統停止更新。
     *
     * @return Collection<int, DailyStatistic>
     */
    public function trends(int $days = 14): Collection
    {
        $history = Cache::remember("dashboard:trends:{$days}", self::TRENDS_CACHE_TTL_SECONDS, function () use ($days) {
            return DailyStatistic::query()
                ->where('date', '>=', Date::today()->subDays($days))
                ->orderBy('date')
                ->get();
        });

        if ($history->contains(fn (DailyStatistic $stat) => $stat->date->isToday())) {
            return $history;
        }

        $today = (new DailyStatistic([
            'date' => Date::today(),
            'search_count' => $this->todaySearchCount(),
            'new_posts_count' => $this->todayNewPostsCount(),
            'updated_posts_count' => $this->todayUpdatedPostsCount(),
            'notification_count' => $this->todayNotificationCount(),
        ]));

        return $history->push($today);
    }
}
