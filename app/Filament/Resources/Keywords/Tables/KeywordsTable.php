<?php

namespace App\Filament\Resources\Keywords\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class KeywordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('關鍵字')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('啟用')
                    ->boolean(),
                TextColumn::make('crawl_interval_min')
                    ->label('巡檢頻率')
                    ->formatStateUsing(fn (int $state) => $state >= 60 ? '每 ' . intdiv($state, 60) . ' 小時' : "每 {$state} 分鐘")
                    ->sortable(),
                TextColumn::make('time_range_type')
                    ->label('時間範圍')
                    ->badge(),
                TextColumn::make('thresholds_count')
                    ->label('門檻數')
                    ->counts('thresholds'),
                TextColumn::make('last_crawled_at')
                    ->label('最後巡檢時間')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('尚未巡檢'),
                TextColumn::make('created_at')
                    ->label('建立時間')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('啟用狀態'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
