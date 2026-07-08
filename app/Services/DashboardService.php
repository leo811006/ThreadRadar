<?php

namespace App\Services;

use App\Models\CrawlLog;
use App\Models\Keyword;
use App\Models\NotificationLog;
use App\Models\Post;
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

    public function todaySearchCount(): int
    {
        return Cache::remember('dashboard:today_search_count', self::CACHE_TTL_SECONDS, function () {
            return CrawlLog::whereDate('started_at', Date::today())->count();
        });
    }

    public function todayNewPostsCount(): int
    {
        return Cache::remember('dashboard:today_new_posts_count', self::CACHE_TTL_SECONDS, function () {
            return Post::whereDate('first_seen_at', Date::today())->count();
        });
    }

    public function todayUpdatedPostsCount(): int
    {
        return Cache::remember('dashboard:today_updated_posts_count', self::CACHE_TTL_SECONDS, function () {
            return Post::whereDate('last_seen_at', Date::today())
                ->whereColumn('last_seen_at', '!=', 'first_seen_at')
                ->count();
        });
    }

    public function todayNotificationCount(): int
    {
        return Cache::remember('dashboard:today_notification_count', self::CACHE_TTL_SECONDS, function () {
            return NotificationLog::where('status', 'sent')
                ->whereDate('sent_at', Date::today())
                ->count();
        });
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
}
