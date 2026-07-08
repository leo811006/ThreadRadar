<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordThreshold extends Model
{
    use HasFactory;

    protected $fillable = [
        'keyword_id',
        'group',
        'metric',
        'operator',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'group' => 'integer',
            'value' => 'integer',
        ];
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
