<?php

namespace App\Services;

use App\Models\Keyword;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($data) {
            $keyword = Keyword::create(Arr::except($data, ['thresholds', 'notification_channels']));

            $this->syncThresholds($keyword, $data['thresholds'] ?? null);
            $this->syncNotificationChannels($keyword, $data['notification_channels'] ?? null);

            return $keyword->load(['thresholds', 'notificationChannels']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Keyword $keyword, array $data): Keyword
    {
        return DB::transaction(function () use ($keyword, $data) {
            $keyword->update(Arr::except($data, ['thresholds', 'notification_channels']));

            if (array_key_exists('thresholds', $data)) {
                $this->syncThresholds($keyword, $data['thresholds']);
            }

            if (array_key_exists('notification_channels', $data)) {
                $this->syncNotificationChannels($keyword, $data['notification_channels']);
            }

            return $keyword->load(['thresholds', 'notificationChannels']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $thresholds
     */
    private function syncThresholds(Keyword $keyword, ?array $thresholds): void
    {
        if ($thresholds === null) {
            return;
        }

        $thresholds = collect($thresholds)
            ->map(fn (array $threshold) => [...$threshold, 'group' => $threshold['group'] ?? 0])
            ->all();

        $keyword->thresholds()->delete();
        $keyword->thresholds()->createMany($thresholds);
    }

    /**
     * 依 id 比對既有管道：若某筆送來的 config 是空陣列（代表前端未提供新設定值，
     * 例如編輯畫面因 API 遮蔽機敏值而無法回填），保留資料庫現有的 config，避免
     * 使用者未觸碰通知設定就儲存時把 webhook_url/bot_token 等既有機敏值清空。
     *
     * @param  array<int, array<string, mixed>>|null  $channels
     */
    private function syncNotificationChannels(Keyword $keyword, ?array $channels): void
    {
        if ($channels === null) {
            return;
        }

        $existingConfigById = $keyword->notificationChannels()->pluck('config', 'id');

        $channels = collect($channels)->map(function (array $channel) use ($existingConfigById) {
            $id = $channel['id'] ?? null;

            if (empty($channel['config']) && $id !== null && $existingConfigById->has($id)) {
                $channel['config'] = $existingConfigById->get($id);
            }

            return Arr::except($channel, ['id']);
        })->all();

        $keyword->notificationChannels()->delete();
        $keyword->notificationChannels()->createMany($channels);
    }
}
