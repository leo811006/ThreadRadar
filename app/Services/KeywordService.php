<?php

namespace App\Services;

use App\Models\Keyword;
use Illuminate\Support\Arr;

/**
 * 關鍵字 CRUD 與門檻/通知管道子資源的業務規則（FR-1, FR-2）。
 */
class KeywordService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Keyword
    {
        $keyword = Keyword::create(Arr::except($data, ['thresholds', 'notification_channels']));

        $this->syncThresholds($keyword, $data['thresholds'] ?? null);
        $this->syncNotificationChannels($keyword, $data['notification_channels'] ?? null);

        return $keyword->load(['thresholds', 'notificationChannels']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Keyword $keyword, array $data): Keyword
    {
        $keyword->update(Arr::except($data, ['thresholds', 'notification_channels']));

        if (array_key_exists('thresholds', $data)) {
            $this->syncThresholds($keyword, $data['thresholds']);
        }

        if (array_key_exists('notification_channels', $data)) {
            $this->syncNotificationChannels($keyword, $data['notification_channels']);
        }

        return $keyword->load(['thresholds', 'notificationChannels']);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $thresholds
     */
    private function syncThresholds(Keyword $keyword, ?array $thresholds): void
    {
        if ($thresholds === null) {
            return;
        }

        $keyword->thresholds()->delete();
        $keyword->thresholds()->createMany($thresholds);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $channels
     */
    private function syncNotificationChannels(Keyword $keyword, ?array $channels): void
    {
        if ($channels === null) {
            return;
        }

        $keyword->notificationChannels()->delete();
        $keyword->notificationChannels()->createMany($channels);
    }
}
