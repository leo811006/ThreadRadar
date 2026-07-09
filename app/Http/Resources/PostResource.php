<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'threads_url' => $this->threads_url,
            'author_name' => $this->author_name,
            'author_username' => $this->author_username,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'content' => $this->content,
            'images' => $this->images,
            'videos' => $this->videos,
            'views_count' => $this->views_count,
            'likes_count' => $this->likes_count,
            'replies_count' => $this->replies_count,
            'reposts_count' => $this->reposts_count,
            'quotes_count' => $this->quotes_count,
            'is_verified_author' => $this->is_verified_author,
            'keywords' => $this->whenLoaded('keywords', fn () => $this->keywords->pluck('name')),
            'ai_summary' => $this->ai_summary,
            'ai_sentiment' => $this->ai_sentiment,
            'ai_tags' => $this->ai_tags,
            'first_seen_at' => $this->first_seen_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
