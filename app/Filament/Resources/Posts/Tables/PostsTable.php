<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Keyword;
use App\Models\Post;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('author_name')
                    ->label('作者')
                    ->description(fn ($record) => '@' . $record->author_username)
                    ->searchable(['author_name', 'author_username']),
                TextColumn::make('content')
                    ->label('內容')
                    ->limit(60)
                    ->searchable(),
                IconColumn::make('is_verified_author')
                    ->label('驗證')
                    ->boolean(),
                TextColumn::make('hotness_score')
                    ->label('熱門度')
                    ->state(fn ($record) => $record->views_count + $record->likes_count * 5 + $record->replies_count * 3 + $record->reposts_count * 4 + $record->quotes_count * 4)
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByHotness($direction)),
                TextColumn::make('views_count')->label('Views')->numeric()->sortable(),
                TextColumn::make('likes_count')->label('Likes')->numeric()->sortable(),
                TextColumn::make('replies_count')->label('Replies')->numeric()->sortable(),
                TextColumn::make('reposts_count')->label('Reposts')->numeric()->sortable(),
                TextColumn::make('quotes_count')->label('Quotes')->numeric()->sortable(),
                TextColumn::make('keywords.name')
                    ->label('關鍵字')
                    ->badge(),
                TextColumn::make('ai_summary')
                    ->label('AI 摘要')
                    ->limit(40)
                    ->placeholder('尚未分析')
                    ->toggleable(),
                TextColumn::make('ai_sentiment')
                    ->label('AI 情緒')
                    ->badge()
                    ->placeholder('尚未分析')
                    ->color(fn (?string $state) => match ($state) {
                        'positive' => 'success',
                        'negative' => 'danger',
                        'neutral' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(),
                IconColumn::make('ai_analysis_failed_at')
                    ->label('AI 分析失敗')
                    ->boolean()
                    ->state(fn (Post $record) => $record->ai_analysis_failed_at !== null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('posted_at')
                    ->label('發文時間')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('keywords')
                    ->label('關鍵字')
                    ->relationship('keywords', 'name')
                    ->options(fn () => Keyword::pluck('name', 'id')),
                TernaryFilter::make('is_verified_author')
                    ->label('是否驗證帳號'),
                SelectFilter::make('ai_sentiment')
                    ->label('AI 情緒')
                    ->options([
                        'positive' => '正面',
                        'negative' => '負面',
                        'neutral' => '中立',
                    ]),
                TernaryFilter::make('ai_analysis_failed_at')
                    ->label('AI 分析失敗')
                    ->nullable()
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('ai_analysis_failed_at'),
                        false: fn (Builder $query) => $query->whereNull('ai_analysis_failed_at'),
                    ),
                Filter::make('posted_at')
                    ->schema([
                        DatePicker::make('posted_from')->label('發文時間從'),
                        DatePicker::make('posted_until')->label('發文時間至'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['posted_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('posted_at', '>=', $date))
                            ->when($data['posted_until'] ?? null, fn (Builder $q, $date) => $q->whereDate('posted_at', '<=', $date));
                    }),
            ])
            ->defaultSort('posted_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
