<?php

namespace App\Services;

use App\Data\PostData;
use App\Models\Post;
use Illuminate\Support\Facades\Date;

/**
 * 依 threads_url 去重：已存在 → 僅更新互動數與 last_seen_at；不存在 → 建立新紀錄。
 * 內容類欄位（作者名稱、文章內容）保留首次值，互動數類欄位永遠覆蓋為最新值（見 docs/05-database-schema.md §3）。
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

        $post->views_count = $data->viewsCount;
        $post->likes_count = $data->likesCount;
        $post->replies_count = $data->repliesCount;
        $post->reposts_count = $data->repostsCount;
        $post->quotes_count = $data->quotesCount;
        $post->last_seen_at = $now;

        $post->save();

        $post->metricSnapshots()->create([
            'views_count' => $data->viewsCount,
            'likes_count' => $data->likesCount,
            'replies_count' => $data->repliesCount,
            'reposts_count' => $data->repostsCount,
            'quotes_count' => $data->quotesCount,
            'recorded_at' => $now,
        ]);

        // save() 之後 Eloquent 的 wasRecentlyCreated 才具語意，於此時機點賦值可靠地
        // 讓呼叫端（CrawlKeywordJob）直接讀取 $post->wasRecentlyCreated 判斷是否為新文章，
        // 不需要再對同一張表額外查一次 exists()。
        $post->wasRecentlyCreated = $isNewPost;

        return $post;
    }
}
