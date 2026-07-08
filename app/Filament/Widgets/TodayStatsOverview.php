<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TodayStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $dashboard = app(DashboardService::class);

        return [
            Stat::make('今日搜尋次數', $dashboard->todaySearchCount()),
            Stat::make('今日新增文章', $dashboard->todayNewPostsCount()),
            Stat::make('今日更新文章', $dashboard->todayUpdatedPostsCount()),
            Stat::make('今日通知次數', $dashboard->todayNotificationCount()),
        ];
    }
}
