<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
        'crawl_interval_min',
        'time_range_type',
        'time_range_custom_from',
        'time_range_custom_to',
        'last_crawled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'crawl_interval_min' => 'integer',
            'time_range_custom_from' => 'datetime',
            'time_range_custom_to' => 'datetime',
            'last_crawled_at' => 'datetime',
        ];
    }

    public function thresholds(): HasMany
    {
        return $this->hasMany(KeywordThreshold::class);
    }

    public function notificationChannels(): HasMany
    {
        return $this->hasMany(KeywordNotificationChannel::class);
    }

    public function postMatches(): HasMany
    {
        return $this->hasMany(PostKeywordMatch::class);
    }

    public function crawlLogs(): HasMany
    {
        return $this->hasMany(CrawlLog::class);
    }
}
