<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('文章資訊')
                    ->columns(2)
                    ->components([
                        TextEntry::make('author_name')->label('作者'),
                        TextEntry::make('author_username')->label('帳號')->formatStateUsing(fn ($state) => "@{$state}"),
                        IconEntry::make('is_verified_author')->label('驗證帳號')->boolean(),
                        TextEntry::make('posted_at')->label('發文時間')->dateTime(),
                        TextEntry::make('threads_url')->label('連結')->url(fn ($state) => $state, shouldOpenInNewTab: true)->columnSpanFull(),
                        TextEntry::make('content')->label('內容')->columnSpanFull(),
                    ]),
                Section::make('互動數')
                    ->columns(5)
                    ->components([
                        TextEntry::make('views_count')->label('Views')->numeric(),
                        TextEntry::make('likes_count')->label('Likes')->numeric(),
                        TextEntry::make('replies_count')->label('Replies')->numeric(),
                        TextEntry::make('reposts_count')->label('Reposts')->numeric(),
                        TextEntry::make('quotes_count')->label('Quotes')->numeric(),
                    ]),
                Section::make('系統資訊')
                    ->columns(3)
                    ->components([
                        TextEntry::make('keywords.name')->label('命中關鍵字')->badge(),
                        TextEntry::make('first_seen_at')->label('首次發現時間')->dateTime(),
                        TextEntry::make('last_seen_at')->label('最後更新時間')->dateTime(),
                    ]),
                Section::make('AI 分析')
                    ->columns(1)
                    ->components([
                        TextEntry::make('ai_summary')->label('摘要')->placeholder('尚未分析')->columnSpanFull(),
                        TextEntry::make('ai_sentiment')->label('情緒')->badge()->placeholder('尚未分析'),
                        TextEntry::make('ai_tags')->label('標籤')->badge()->placeholder('尚未分析'),
                        TextEntry::make('ai_analysis_failure_reason')
                            ->label('分析失敗原因')
                            ->color('danger')
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->ai_analysis_failed_at !== null),
                    ]),
            ]);
    }
}
