<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Date;

class PostController extends Controller
{
    /**
     * FR-4 結果查詢：依關鍵字/作者/日期/是否驗證帳號篩選；依 最新/最熱門/Views/Likes/Replies/Reposts 排序。
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'keyword' => ['sometimes', 'string'],
            'author' => ['sometimes', 'string'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'is_verified_author' => ['sometimes', 'boolean'],
            'is_matched' => ['sometimes', 'boolean'],
            'ai_sentiment' => ['sometimes', 'in:positive,negative,neutral'],
            'sort' => ['sometimes', 'in:latest,hottest,views,likes,replies,reposts'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Post::query()->with('keywords');

        if (! empty($validated['keyword'])) {
            $query->whereHas('keywords', fn ($q) => $q->where('name', $validated['keyword']));
        }

        if (! empty($validated['author'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('author_name', 'like', "%{$validated['author']}%")
                    ->orWhere('author_username', 'like', "%{$validated['author']}%");
            });
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate('posted_at', '>=', Date::parse($validated['date_from']));
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('posted_at', '<=', Date::parse($validated['date_to']));
        }

        if (array_key_exists('is_verified_author', $validated)) {
            $query->where('is_verified_author', $validated['is_verified_author']);
        }

        if (array_key_exists('is_matched', $validated)) {
            $query->has('keywordMatches', $validated['is_matched'] ? '>=' : '<', 1);
        }

        if (! empty($validated['ai_sentiment'])) {
            $query->where('ai_sentiment', $validated['ai_sentiment']);
        }

        match ($validated['sort'] ?? 'latest') {
            'hottest' => $query->orderByHotness(),
            'views' => $query->orderByDesc('views_count'),
            'likes' => $query->orderByDesc('likes_count'),
            'replies' => $query->orderByDesc('replies_count'),
            'reposts' => $query->orderByDesc('reposts_count'),
            default => $query->orderByDesc('posted_at'),
        };

        $posts = $query->paginate($validated['per_page'] ?? 15);

        return PostResource::collection($posts);
    }

    public function show(Post $post): PostResource
    {
        return new PostResource($post->load('keywords'));
    }
}
