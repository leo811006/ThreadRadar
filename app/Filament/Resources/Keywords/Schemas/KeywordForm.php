<?php

namespace App\Filament\Resources\Keywords\Schemas;

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
                    ->description('全部條件須同時符合（AND 邏輯）')
                    ->components([
                        Repeater::make('thresholds')
                            ->relationship()
                            ->label('門檻條件')
                            ->columns(3)
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
                            ->addActionLabel('新增門檻條件')
                            ->defaultItems(0),
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
}
