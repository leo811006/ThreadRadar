<?php

namespace App\Filament\Widgets;

use App\Models\Post;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopPostsWidget extends TableWidget
{
    protected static ?string $heading = '熱門文章 TOP20';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Post::query()->orderByHotness()->limit(20))
            ->paginated(false)
            ->columns([
                TextColumn::make('author_name')->label('作者'),
                TextColumn::make('content')->label('內容')->limit(50),
                TextColumn::make('views_count')->label('Views')->numeric(),
                TextColumn::make('likes_count')->label('Likes')->numeric(),
                TextColumn::make('posted_at')->label('發文時間')->dateTime(),
            ])
            ->recordUrl(fn (Post $record) => route('filament.admin.resources.posts.view', $record));
    }
}
