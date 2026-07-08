<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * config 欄位可能含 webhook URL / bot token 等機敏值，API 回應一律遮蔽，避免外洩。
 * 若需修改 config，須透過專門的 update 端點（不在此 Resource 的回應路徑上）。
 */
class KeywordNotificationChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel_type' => $this->channel_type,
            'is_active' => $this->is_active,
            'config' => '******',
        ];
    }
}
