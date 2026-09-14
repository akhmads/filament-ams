<?php

namespace App\Filament\Widgets;

use App\Enums\AssetStatus;
use App\Models\Asset;
use Filament\Widgets\ChartWidget;

class AssetsByStatusChart extends ChartWidget
{
    protected ?string $heading = 'Assets by Status';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = Asset::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = [];
        $values = [];

        foreach (AssetStatus::cases() as $status) {
            $total = (int) ($counts[$status->value] ?? 0);

            if ($total === 0) {
                continue;
            }

            $labels[] = $status->getLabel();
            $values[] = $total;
        }

        return [
            'datasets' => [[
                'label' => 'Asset count',
                'data' => $values,
                'backgroundColor' => [
                    '#10b981', '#3b82f6', '#94a3b8', '#f59e0b',
                    '#f97316', '#ef4444', '#dc2626', '#64748b', '#334155',
                ],
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
