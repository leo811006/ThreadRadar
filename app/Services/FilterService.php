<?php

namespace App\Services;

use App\Data\PostData;
use App\Models\Keyword;
use App\Models\KeywordThreshold;
use InvalidArgumentException;

/**
 * 判斷一篇貼文是否符合關鍵字設定的熱門度門檻。
 *
 * 門檻以 group 分組：同一 group 內為 AND，不同 group 之間為 OR
 * （例：group 0 = 「likes >= 500」、group 1 = 「likes >= 300 AND replies >= 10」，
 * 只要任一 group 全數符合即算命中）。
 */
class FilterService
{
    public function matchesThreshold(PostData $post, Keyword $keyword): bool
    {
        $thresholds = $keyword->thresholds;

        if ($thresholds->isEmpty()) {
            return true;
        }

        return $thresholds
            ->groupBy('group')
            ->contains(
                fn ($groupThresholds) => $groupThresholds->every(
                    fn (KeywordThreshold $threshold) => $this->matchesSingleThreshold($post, $threshold)
                )
            );
    }

    private function matchesSingleThreshold(PostData $post, KeywordThreshold $threshold): bool
    {
        $actual = $this->extractMetricValue($post, $threshold->metric);

        // 資料來源無法提供此指標（如非官方爬蟲不提供互動數，見 PostData 說明）時，
        // 一律視為不符合，而非把 null 當成 0 比較——0 會讓 '<'/'<=' 恆為真造成誤報，
        // 讓 '>'/'>=' 恆為假則使門檻永久失效，兩者都是「假裝有資料」的錯誤呈現。
        if ($actual === null) {
            return false;
        }

        return match ($threshold->operator) {
            '>' => $actual > $threshold->value,
            '>=' => $actual >= $threshold->value,
            '=' => $actual === $threshold->value,
            '<' => $actual < $threshold->value,
            '<=' => $actual <= $threshold->value,
            default => throw new InvalidArgumentException("Unsupported operator: {$threshold->operator}"),
        };
    }

    private function extractMetricValue(PostData $post, string $metric): ?int
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
