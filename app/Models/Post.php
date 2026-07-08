<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasFactory;

    /**
     * 綜合熱門度公式：互動類指標依社群影響力加權，views 影響力最低權重為 1。
     */
    public const HOTNESS_SCORE_EXPRESSION = 'views_count + likes_count * 5 + replies_count * 3 + reposts_count * 4 + quotes_count * 4';

    protected $fillable = [
        'threads_url',
        'author_name',
        'author_username',
        'posted_at',
        'content',
        'images',
        'videos',
        'views_count',
        'likes_count',
        'replies_count',
        'reposts_count',
        'quotes_count',
        'is_verified_author',
        'ai_summary',
        'ai_tags',
        'ai_sentiment',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'images' => 'array',
            'videos' => 'array',
            'views_count' => 'integer',
            'likes_count' => 'integer',
            'replies_count' => 'integer',
            'reposts_count' => 'integer',
            'quotes_count' => 'integer',
            'is_verified_author' => 'boolean',
            'ai_tags' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function keywordMatches(): HasMany
    {
        return $this->hasMany(PostKeywordMatch::class);
    }

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class, 'post_keyword_matches')
            ->withPivot(['matched_at', 'notified_at']);
    }

    public function metricSnapshots(): HasMany
    {
        return $this->hasMany(PostMetricSnapshot::class);
    }

    public function scopeOrderByHotness(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderByRaw(self::HOTNESS_SCORE_EXPRESSION . ' ' . ($direction === 'asc' ? 'asc' : 'desc'));
    }
}
