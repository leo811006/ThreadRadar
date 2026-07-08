<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMetricSnapshot extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'views_count',
        'likes_count',
        'replies_count',
        'reposts_count',
        'quotes_count',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'views_count' => 'integer',
            'likes_count' => 'integer',
            'replies_count' => 'integer',
            'reposts_count' => 'integer',
            'quotes_count' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
