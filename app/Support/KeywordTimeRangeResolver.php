<?php

namespace App\Support;

use App\Models\Keyword;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * 將關鍵字設定的 time_range_type 轉換為實際的查詢起始時間點。
 */
class KeywordTimeRangeResolver
{
    public function resolveSince(Keyword $keyword): CarbonImmutable
    {
        if ($keyword->time_range_type === 'custom') {
            if ($keyword->time_range_custom_from === null) {
                throw new InvalidArgumentException("Keyword #{$keyword->id} has custom time range but no time_range_custom_from set.");
            }

            return CarbonImmutable::instance($keyword->time_range_custom_from);
        }

        $now = CarbonImmutable::now();

        return match ($keyword->time_range_type) {
            '30min' => $now->subMinutes(30),
            '1h' => $now->subHour(),
            '6h' => $now->subHours(6),
            '24h' => $now->subDay(),
            '7d' => $now->subDays(7),
            default => throw new InvalidArgumentException("Unsupported time_range_type: {$keyword->time_range_type}"),
        };
    }

    public function resolveUntil(Keyword $keyword): ?CarbonImmutable
    {
        if ($keyword->time_range_type === 'custom' && $keyword->time_range_custom_to !== null) {
            return CarbonImmutable::instance($keyword->time_range_custom_to);
        }

        return null;
    }
}
