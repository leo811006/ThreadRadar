<?php

namespace App\Filament\Resources\Keywords\Schemas;

use App\Models\KeywordThreshold;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KeywordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('基本設定')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('關鍵字')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('啟用')
                            ->default(true),
                        Select::make('crawl_interval_min')
                            ->label('巡檢頻率')
                            ->options([
                                1 => '每 1 分鐘',
                                5 => '每 5 分鐘',
                                10 => '每 10 分鐘',
                                30 => '每 30 分鐘',
                                60 => '每 1 小時',
                            ])
                            ->required(),
                        Select::make('time_range_type')
                            ->label('搜尋時間範圍')
                            ->options([
                                '30min' => '最近 30 分鐘',
                                '1h' => '最近 1 小時',
                                '6h' => '最近 6 小時',
                                '24h' => '最近 24 小時',
                                '7d' => '最近 7 天',
                                'custom' => '自訂日期',
                            ])
                            ->live()
                            ->required(),
                        DateTimePicker::make('time_range_custom_from')
                            ->label('自訂起始時間')
                            ->visible(fn ($get) => $get('time_range_type') === 'custom')
                            ->required(fn ($get) => $get('time_range_type') === 'custom'),
                        DateTimePicker::make('time_range_custom_to')
                            ->label('自訂結束時間')
                            ->visible(fn ($get) => $get('time_range_type') === 'custom'),
                    ]),

                Section::make('熱門度門檻')
                    ->description('組間為 OR、組內為 AND：任一組內的條件全數符合即算命中（例如「likes>=500」一組，「likes>=300 且 replies>=10」另一組）')
                    ->components([
                        Repeater::make('thresholds')
                            ->label('門檻組')
                            ->columns(1)
                            ->components([
                                Repeater::make('conditions')
                                    ->label('組內條件（AND）')
                                    ->columns(3)
                                    ->minItems(1)
                                    ->components([
                                        Select::make('metric')
                                            ->label('指標')
                                            ->options([
                                                'views' => 'Views',
                                                'likes' => 'Likes',
                                                'replies' => 'Replies',
                                                'reposts' => 'Reposts',
                                                'quotes' => 'Quotes',
                                            ])
                                            ->required(),
                                        Select::make('operator')
                                            ->label('運算子')
                                            ->options([
                                                '>' => '>',
                                                '>=' => '>=',
                                                '=' => '=',
                                                '<' => '<',
                                                '<=' => '<=',
                                            ])
                                            ->required(),
                                        TextInput::make('value')
                                            ->label('數值')
                                            ->numeric()
                                            ->minValue(0)
                                            ->required(),
                                    ])
                                    ->addActionLabel('新增條件')
                                    ->defaultItems(1),
                            ])
                            ->addActionLabel('新增門檻組（OR）')
                            ->defaultItems(0)
                            ->dehydrateStateUsing(fn (array $state) => self::flattenThresholdGroups($state))
                            ->afterStateHydrated(function (Repeater $component, $state) {
                                $thresholds = $component->getRecord()?->thresholds;

                                if ($thresholds === null || $thresholds->isEmpty()) {
                                    return;
                                }

                                $component->state(
                                    $thresholds
                                        ->groupBy('group')
                                        ->values()
                                        ->map(fn ($conditions) => [
                                            'conditions' => $conditions
                                                ->map(fn (KeywordThreshold $t) => [
                                                    'metric' => $t->metric,
                                                    'operator' => $t->operator,
                                                    'value' => $t->value,
                                                ])
                                                ->all(),
                                        ])
                                        ->all()
                                );
                            }),
                    ]),

                Section::make('通知管道')
                    ->components([
                        Repeater::make('notificationChannels')
                            ->relationship()
                            ->label('通知設定')
                            ->columns(3)
                            ->components([
                                Select::make('channel_type')
                                    ->label('管道')
                                    ->options([
                                        'email' => 'Email',
                                        'discord' => 'Discord',
                                        'slack' => 'Slack',
                                        'line' => 'LINE',
                                        'telegram' => 'Telegram',
                                        'webhook' => 'Webhook',
                                    ])
                                    ->required()
                                    ->live(),
                                KeyValue::make('config')
                                    ->label('設定（key-value）')
                                    ->helperText('例如 webhook_url、bot_token、to、channel_access_token 等，依管道類型而異')
                                    ->columnSpan(2),
                                Toggle::make('is_active')
                                    ->label('啟用')
                                    ->default(true),
                            ])
                            ->addActionLabel('新增通知管道')
                            ->defaultItems(0),
                    ]),
            ]);
    }

    /**
     * 把巢狀的「門檻組（OR）→ 組內條件（AND）」Repeater 狀態攤平成
     * KeywordThreshold 可直接寫入的扁平陣列，並補上 group 編號。
     *
     * 過濾掉 metric/operator/value 任一欄位為空的條件：Repeater 的
     * defaultItems(1) 會預先給一個空白列，使用者若新增門檻組後未實際
     * 填寫條件就送出表單，這種殘缺資料不該被寫入資料庫（value 欄位
     * 無預設值，直接寫入會產生 SQL 錯誤）。
     *
     * @param  array<int, array{conditions?: array<int, array<string, mixed>>}>  $state
     * @return array<int, array<string, mixed>>
     */
    public static function flattenThresholdGroups(array $state): array
    {
        return collect($state)
            ->map(fn (array $group) => collect($group['conditions'] ?? [])
                ->filter(fn (array $condition) => filled($condition['metric'] ?? null)
                    && filled($condition['operator'] ?? null)
                    && filled($condition['value'] ?? null)))
            ->reject(fn ($conditions) => $conditions->isEmpty())
            ->values()
            ->flatMap(
                fn ($conditions, int $groupIndex) => $conditions
                    ->map(fn (array $condition) => [...$condition, 'group' => $groupIndex])
            )
            ->all();
    }
}
