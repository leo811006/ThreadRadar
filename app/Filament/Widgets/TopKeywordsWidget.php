<?php

namespace App\Filament\Widgets;

use App\Models\Keyword;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopKeywordsWidget extends TableWidget
{
    protected static ?string $heading = '熱門關鍵字 TOP20';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Keyword::query()
                ->withCount('postMatches')
                ->orderByDesc('post_matches_count')
                ->limit(20))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('關鍵字'),
                IconColumn::make('is_active')->label('啟用')->boolean(),
                TextColumn::make('post_matches_count')->label('命中文章數')->numeric(),
            ])
            ->recordUrl(fn (Keyword $record) => route('filament.admin.resources.keywords.edit', $record));
    }
}
