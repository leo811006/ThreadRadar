<?php

namespace App\Services;

use App\Data\PostData;
use App\Models\Post;
use Illuminate\Support\Facades\Date;
use LogicException;

/**
 * 依 threads_url 去重：已存在 → 僅更新互動數與 last_seen_at；不存在 → 建立新紀錄。
 * 內容類欄位（作者名稱、文章內容）保留首次值，互動數類欄位永遠覆蓋為最新值（見 docs/05-database-schema.md §3）。
 *
 * 例外：PostData 的互動數為 null（資料來源無法提供，如非官方爬蟲）時不覆蓋既有值——
 * 否則會用「不知道」抹掉先前來源（如官方 API）留下的真實數字，見 PostData 的 null 語意說明。
 */
class PostUpsertService
{
    public function upsert(PostData $data): Post
    {
        $now = Date::now();

        $post = Post::firstOrNew(['threads_url' => $data->threadsUrl]);
        $isNewPost = ! $post->exists;

        if ($isNewPost) {
            $post->author_name = $data->authorName;
            $post->author_username = $data->authorUsername;
            $post->posted_at = $data->postedAt;
            $post->content = $data->content;
            $post->images = $data->images;
            $post->videos = $data->videos;
            $post->is_verified_author = $data->isVerifiedAuthor;
            $post->first_seen_at = $now;
        }

        // views/quotes 與 likes/replies/reposts 來自資料來源不同層級的能力限制，須
        // 分開檢查，不可視為同一組「必須同時 null 或同時有值」的欄位：
        // - views/quotes：搜尋結果頁 DOM 結構性缺失（views 只在貼文詳情頁才有、
        //   quotes 在 Threads 前端本身沒有統計欄位），這兩者對非官方爬蟲來源
        //   永遠是 null，與 likes/replies/reposts 是否抓到值無關。
        // - likes/replies/reposts：同屬「動作列按鈕」這一類，爬蟲能否解析出數字
        //   取決於同一段 DOM 擷取邏輯，理論上應同時成功或同時失敗；若只有部分
        //   欄位缺值，代表擷取邏輯本身出錯（如 aria-label 對照表過時、數字格式
        //   跳脫既有正則），需要直接失敗讓人查明，而非靜默寫入不一致的資料。
        //
        // 見 2026-07-10 事故：初版誤將五個欄位當成同一組，只要爬蟲來源同時抓到
        // likes/replies/reposts（views/quotes 依然是 null）就必然觸發例外。
        $viewsQuotesMetrics = [$data->viewsCount, $data->quotesCount];
        $viewsQuotesNullCount = count(array_filter($viewsQuotesMetrics, fn ($value) => $value === null));

        if ($viewsQuotesNullCount !== 0 && $viewsQuotesNullCount !== count($viewsQuotesMetrics)) {
            throw new LogicException(
                'PostData 的 views/quotes 欄位必須同時為 null 或同時有值，不允許部分缺值。'
            );
        }

        $engagementMetrics = [$data->likesCount, $data->repliesCount, $data->repostsCount];
        $engagementNullCount = count(array_filter($engagementMetrics, fn ($value) => $value === null));

        if ($engagementNullCount !== 0 && $engagementNullCount !== count($engagementMetrics)) {
            throw new LogicException(
                'PostData 的 likes/replies/reposts 欄位必須同時為 null 或同時有值，不允許部分缺值。'
            );
        }

        $hasViewsQuotes = $viewsQuotesNullCount === 0;
        $hasEngagement = $engagementNullCount === 0;

        if ($hasViewsQuotes) {
            $post->views_count = $data->viewsCount;
            $post->quotes_count = $data->quotesCount;
        } elseif ($isNewPost) {
            // 新貼文若資料來源本來就沒有這兩項數據，欄位保持資料庫預設值（0），
            // 而非留 null——這代表「尚無數據」而非「經比對後確認為 0」。
            $post->views_count = 0;
            $post->quotes_count = 0;
        }

        if ($hasEngagement) {
            $post->likes_count = $data->likesCount;
            $post->replies_count = $data->repliesCount;
            $post->reposts_count = $data->repostsCount;
        } elseif ($isNewPost) {
            $post->likes_count = 0;
            $post->replies_count = 0;
            $post->reposts_count = 0;
        }

        $post->last_seen_at = $now;

        $post->save();

        // post_metric_snapshots 各欄位皆為 NOT NULL、無法表示「本次未觀測」，故缺值
        // 那組不可比照 $post 新貼文時的做法寫死 0——對既有貼文而言，0 會與該貼文
        // 其他來源早已寫入的真實非零值互相矛盾，讓快照時間序出現假的歸零。改用
        // $post 當下已持久化的欄位值（save() 後即代表「最後已知狀態」，新貼文若
        // 缺值也已於上方 elseif ($isNewPost) 落成 0）填入缺值那組，語意上等同
        // 「本次沒有新觀測，快照沿用最後已知數字」而非「觀測到 0」。
        //
        // 僅在至少一組有新觀測、且與上一筆快照數值不同時才寫入，避免爬蟲來源
        // （views/quotes 永遠缺值）每次巡檢都無條件新增一筆重複快照，造成
        // post_metric_snapshots 無節制成長。
        if ($hasViewsQuotes || $hasEngagement) {
            $snapshotValues = [
                'views_count' => $post->views_count,
                'likes_count' => $post->likes_count,
                'replies_count' => $post->replies_count,
                'reposts_count' => $post->reposts_count,
                'quotes_count' => $post->quotes_count,
            ];

            $latestSnapshot = $post->metricSnapshots()->latest('recorded_at')->first();
            $unchanged = $latestSnapshot !== null
                && $latestSnapshot->views_count === $snapshotValues['views_count']
                && $latestSnapshot->likes_count === $snapshotValues['likes_count']
                && $latestSnapshot->replies_count === $snapshotValues['replies_count']
                && $latestSnapshot->reposts_count === $snapshotValues['reposts_count']
                && $latestSnapshot->quotes_count === $snapshotValues['quotes_count'];

            if (! $unchanged) {
                $post->metricSnapshots()->create([...$snapshotValues, 'recorded_at' => $now]);
            }
        }

        // save() 之後 Eloquent 的 wasRecentlyCreated 才具語意，於此時機點賦值可靠地
        // 讓呼叫端（CrawlKeywordJob）直接讀取 $post->wasRecentlyCreated 判斷是否為新文章，
        // 不需要再對同一張表額外查一次 exists()。
        $post->wasRecentlyCreated = $isNewPost;

        return $post;
    }
}
