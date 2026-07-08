<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KeywordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'crawl_interval_min' => $this->crawl_interval_min,
            'time_range_type' => $this->time_range_type,
            'time_range_custom_from' => $this->time_range_custom_from?->toIso8601String(),
            'time_range_custom_to' => $this->time_range_custom_to?->toIso8601String(),
            'last_crawled_at' => $this->last_crawled_at?->toIso8601String(),
            'thresholds' => KeywordThresholdResource::collection($this->whenLoaded('thresholds')),
            'notification_channels' => KeywordNotificationChannelResource::collection($this->whenLoaded('notificationChannels')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
