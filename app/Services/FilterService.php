<?php

namespace App\Services;

use App\Data\PostData;
use App\Models\Keyword;
use App\Models\KeywordThreshold;
use InvalidArgumentException;

/**
 * 判斷一篇貼文是否符合關鍵字設定的全部熱門度門檻（AND 邏輯）。
 */
class FilterService
{
    public function matchesThreshold(PostData $post, Keyword $keyword): bool
    {
        $thresholds = $keyword->thresholds;

        if ($thresholds->isEmpty()) {
            return true;
        }

        return $thresholds->every(
            fn (KeywordThreshold $threshold) => $this->matchesSingleThreshold($post, $threshold)
        );
    }

    private function matchesSingleThreshold(PostData $post, KeywordThreshold $threshold): bool
    {
        $actual = $this->extractMetricValue($post, $threshold->metric);

        return match ($threshold->operator) {
            '>' => $actual > $threshold->value,
            '>=' => $actual >= $threshold->value,
            '=' => $actual === $threshold->value,
            '<' => $actual < $threshold->value,
            '<=' => $actual <= $threshold->value,
            default => throw new InvalidArgumentException("Unsupported operator: {$threshold->operator}"),
        };
    }

    private function extractMetricValue(PostData $post, string $metric): int
    {
        return match ($metric) {
            'views' => $post->viewsCount,
            'likes' => $post->likesCount,
            'replies' => $post->repliesCount,
            'reposts' => $post->repostsCount,
            'quotes' => $post->quotesCount,
            default => throw new InvalidArgumentException("Unsupported metric: {$metric}"),
        };
    }
}
