<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiAnalysisStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $stats = app(DashboardService::class)->aiAnalysisFailureStats();

        return [
            Stat::make('已嘗試 AI 分析', $stats->attempted),
            Stat::make('永久失敗數', $stats->failed),
            Stat::make('失敗率', $stats->attempted > 0 ? "{$stats->failure_rate}%" : '尚無資料')
                ->color(match (true) {
                    $stats->attempted === 0 => 'gray',
                    $stats->failure_rate > 20 => 'danger',
                    default => 'success',
                }),
        ];
    }
}
