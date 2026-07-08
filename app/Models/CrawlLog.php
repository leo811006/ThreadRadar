<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'keyword_id',
        'status',
        'posts_found',
        'posts_created',
        'posts_updated',
        'api_calls_used',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'posts_found' => 'integer',
            'posts_created' => 'integer',
            'posts_updated' => 'integer',
            'api_calls_used' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
