<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'post_keyword_match_id',
        'channel_type',
        'status',
        'payload',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function postKeywordMatch(): BelongsTo
    {
        return $this->belongsTo(PostKeywordMatch::class);
    }
}
