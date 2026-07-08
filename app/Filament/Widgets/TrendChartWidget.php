<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\ChartWidget;

class TrendChartWidget extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = '近 14 日趨勢';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $trends = app(DashboardService::class)->trends(14);

        return [
            'datasets' => [
                [
                    'label' => '新增文章數',
                    'data' => $trends->pluck('new_posts_count')->all(),
                ],
                [
                    'label' => '通知次數',
                    'data' => $trends->pluck('notification_count')->all(),
                ],
                [
                    'label' => '更新文章數',
                    'data' => $trends->pluck('updated_posts_count')->all(),
                ],
            ],
            'labels' => $trends->pluck('date')->map(fn ($date) => $date->format('m/d'))->all(),
        ];
    }
}
