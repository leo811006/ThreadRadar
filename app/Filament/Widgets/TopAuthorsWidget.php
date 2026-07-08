<?php

namespace App\Filament\Widgets;

use App\Models\Post;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopAuthorsWidget extends TableWidget
{
    protected static ?string $heading = '熱門作者 TOP20';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Post::query()
                ->selectRaw('author_username, MAX(author_name) as author_name, COUNT(*) as post_count, SUM(' . Post::HOTNESS_SCORE_EXPRESSION . ') as total_hotness')
                ->groupBy('author_username')
                ->orderByDesc('total_hotness')
                ->limit(20))
            ->defaultKeySort(false)
            ->paginated(false)
            ->columns([
                TextColumn::make('author_name')->label('作者'),
                TextColumn::make('author_username')->label('帳號')->formatStateUsing(fn ($state) => "@{$state}"),
                TextColumn::make('post_count')->label('文章數')->numeric(),
                TextColumn::make('total_hotness')->label('熱門度總分')->numeric(),
            ]);
    }
}
