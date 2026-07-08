<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateKeywordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'crawl_interval_min' => ['sometimes', 'required', 'integer', 'in:1,5,10,30,60'],
            'time_range_type' => ['sometimes', 'required', 'in:30min,1h,6h,24h,7d,custom'],
            'time_range_custom_from' => ['nullable', 'date'],
            'time_range_custom_to' => ['nullable', 'date', 'after_or_equal:time_range_custom_from'],

            'thresholds' => ['sometimes', 'array'],
            'thresholds.*.group' => ['nullable', 'integer', 'min:0'],
            'thresholds.*.metric' => ['required_with:thresholds', 'in:views,likes,replies,reposts,quotes'],
            'thresholds.*.operator' => ['required_with:thresholds', 'in:>,>=,=,<,<='],
            'thresholds.*.value' => ['required_with:thresholds', 'integer', 'min:0'],

            'notification_channels' => ['sometimes', 'array'],
            'notification_channels.*.id' => ['sometimes', 'integer'],
            'notification_channels.*.channel_type' => ['required_with:notification_channels', 'in:email,discord,slack,line,telegram,webhook'],
            // 更新情境下允許送出空陣列：代表「保留該管道現有的 config，不做變更」
            // （見 KeywordService::syncNotificationChannels，依 id 比對後保留資料庫現值）。
            // 新建立時（StoreKeywordRequest）則不允許空值，必須提供完整設定。
            'notification_channels.*.config' => ['present_with:notification_channels', 'array'],
            'notification_channels.*.is_active' => ['boolean'],
        ];
    }

    /**
     * time_range_custom_from 是否必填取決於「更新後」的 time_range_type，而 PATCH 可能省略
     * time_range_type（沿用資料庫現值），required_if 規則只看本次請求資料無法涵蓋這種情況，
     * 故在此用資料庫現值 + 請求資料合併後的結果額外檢查，避免半套更新造成資料不一致。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $effectiveTimeRangeType = $this->input('time_range_type', $this->route('keyword')?->time_range_type);
            $effectiveFrom = $this->has('time_range_custom_from')
                ? $this->input('time_range_custom_from')
                : $this->route('keyword')?->time_range_custom_from;

            if ($effectiveTimeRangeType === 'custom' && empty($effectiveFrom)) {
                $validator->errors()->add(
                    'time_range_custom_from',
                    'time_range_type 為 custom 時，time_range_custom_from 為必填。'
                );
            }
        });
    }
}
