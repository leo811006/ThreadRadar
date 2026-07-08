<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard): JsonResponse
    {
        return response()->json([
            'data' => [
                'today' => [
                    'search_count' => $dashboard->todaySearchCount(),
                    'new_posts_count' => $dashboard->todayNewPostsCount(),
                    'updated_posts_count' => $dashboard->todayUpdatedPostsCount(),
                    'notification_count' => $dashboard->todayNotificationCount(),
                ],
                'top_posts' => PostResource::collection($dashboard->topPosts()),
                'top_authors' => $dashboard->topAuthors()->map(fn ($author) => [
                    'author_username' => $author->author_username,
                    'author_name' => $author->author_name,
                    'post_count' => $author->post_count,
                    'total_hotness' => (int) $author->total_hotness,
                ]),
                'top_keywords' => $dashboard->topKeywords()->map(fn ($keyword) => [
                    'id' => $keyword->id,
                    'name' => $keyword->name,
                    'post_count' => $keyword->post_matches_count,
                ]),
                'trends' => $dashboard->trends()->map(fn ($stat) => [
                    'date' => $stat->date->toDateString(),
                    'search_count' => $stat->search_count,
                    'new_posts_count' => $stat->new_posts_count,
                    'updated_posts_count' => $stat->updated_posts_count,
                    'notification_count' => $stat->notification_count,
                ]),
            ],
        ]);
    }
}
