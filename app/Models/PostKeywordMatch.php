<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostKeywordMatch extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'post_id',
        'keyword_id',
        'matched_at',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'matched_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
