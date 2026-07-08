<?php

namespace App\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent 內建的 'date' cast 寫入時會經過 fromDateTime()，序列化成
 * 'Y-m-d H:i:s'（非純日期字串），導致 where('date', 'Y-m-d字串') 這種
 * 慣用寫法靜默查無資料（僅 whereDate() 或傳入 Carbon 物件才正確）。
 * 此 cast 強制儲存為純 'Y-m-d'，讓 exact-match 查詢與 whereDate()/範圍
 * 查詢行為一致，讀取時仍回傳 Carbon 物件維持既有呼叫端相容。
 *
 * @implements CastsAttributes<Carbon, string>
 */
class DateOnlyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value)->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toDateString();
    }
}
