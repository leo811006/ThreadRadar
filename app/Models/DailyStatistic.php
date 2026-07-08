<?php

namespace App\Models;

use App\Casts\DateOnlyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'search_count',
        'new_posts_count',
        'updated_posts_count',
        'notification_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => DateOnlyCast::class,
            'search_count' => 'integer',
            'new_posts_count' => 'integer',
            'updated_posts_count' => 'integer',
            'notification_count' => 'integer',
        ];
    }
}
