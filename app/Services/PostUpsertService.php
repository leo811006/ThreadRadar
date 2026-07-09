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

        // 五個互動數欄位須同時為 null 或同時有值——PostData 的資料來源目前只有「完全
        // 不提供互動數」（如爬蟲）或「完全提供」（如官方 API）兩種，沒有欄位級的部分
        // 提供。若未來新增只提供部分指標的來源，這裡會直接失敗，而非靜默寫入不一致的
        // null/0 混合資料，逼迫在新增該來源時明確設計缺值欄位該如何處理。
        $metrics = [
            $data->viewsCount,
            $data->likesCount,
            $data->repliesCount,
            $data->repostsCount,
            $data->quotesCount,
        ];
        $nullCount = count(array_filter($metrics, fn ($value) => $value === null));

        if ($nullCount !== 0 && $nullCount !== count($metrics)) {
            throw new LogicException(
                'PostData 的互動數欄位必須同時為 null 或同時有值，不允許部分缺值。'
            );
        }

        $hasMetrics = $nullCount === 0;

        if ($hasMetrics) {
            $post->views_count = $data->viewsCount;
            $post->likes_count = $data->likesCount;
            $post->replies_count = $data->repliesCount;
            $post->reposts_count = $data->repostsCount;
            $post->quotes_count = $data->quotesCount;
        } elseif ($isNewPost) {
            // 新貼文若資料來源本來就沒有互動數，欄位保持資料庫預設值（0），
            // 而非留 null——這代表「尚無數據」而非「經比對後確認為 0」。
            $post->views_count = 0;
            $post->likes_count = 0;
            $post->replies_count = 0;
            $post->reposts_count = 0;
            $post->quotes_count = 0;
        }

        $post->last_seen_at = $now;

        $post->save();

        if ($hasMetrics) {
            $post->metricSnapshots()->create([
                'views_count' => $data->viewsCount,
                'likes_count' => $data->likesCount,
                'replies_count' => $data->repliesCount,
                'reposts_count' => $data->repostsCount,
                'quotes_count' => $data->quotesCount,
                'recorded_at' => $now,
            ]);
        }

        // save() 之後 Eloquent 的 wasRecentlyCreated 才具語意，於此時機點賦值可靠地
        // 讓呼叫端（CrawlKeywordJob）直接讀取 $post->wasRecentlyCreated 判斷是否為新文章，
        // 不需要再對同一張表額外查一次 exists()。
        $post->wasRecentlyCreated = $isNewPost;

        return $post;
    }
}
