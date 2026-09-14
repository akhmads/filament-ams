<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use Filament\Widgets\ChartWidget;

class AssetsByCategoryChart extends ChartWidget
{
    protected ?string $heading = 'Assets by Category';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = Asset::query()
            ->active()
            ->join('asset_categories', 'assets.asset_category_id', '=', 'asset_categories.id')
            ->selectRaw('asset_categories.name as category, COUNT(*) as total')
            ->groupBy('asset_categories.name')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'category');

        return [
            'datasets' => [[
                'label' => 'Asset count',
                'data' => $rows->values()->all(),
                'backgroundColor' => '#3b82f6',
            ]],
            'labels' => $rows->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
