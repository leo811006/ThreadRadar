<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'time_range_custom_from' => ['required_if:time_range_type,custom', 'nullable', 'date'],
            'time_range_custom_to' => ['nullable', 'date', 'after_or_equal:time_range_custom_from'],

            'thresholds' => ['sometimes', 'array'],
            'thresholds.*.metric' => ['required_with:thresholds', 'in:views,likes,replies,reposts,quotes'],
            'thresholds.*.operator' => ['required_with:thresholds', 'in:>,>=,=,<,<='],
            'thresholds.*.value' => ['required_with:thresholds', 'integer', 'min:0'],

            'notification_channels' => ['sometimes', 'array'],
            'notification_channels.*.channel_type' => ['required_with:notification_channels', 'in:email,discord,slack,line,telegram,webhook'],
            'notification_channels.*.config' => ['required_with:notification_channels', 'array'],
            'notification_channels.*.is_active' => ['boolean'],
        ];
    }
}
