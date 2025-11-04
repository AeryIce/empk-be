<?php

namespace App\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class TrenKunjunganChart extends ChartWidget
{
    protected static ?string $heading = 'Tren Kunjungan (30 hari)';
    protected static ?int $sort = 10;
    protected static ?string $maxHeight = '300px';
    protected int|string|array $columnSpan = ['lg' => 2, '2xl' => 2];

    protected function getData(): array
    {
        $labels = [];
        $data   = [];

        $start = CarbonImmutable::now('Asia/Jakarta')->subDays(29);
        for ($i = 0; $i < 30; $i++) {
            $d = $start->addDays($i);
            $labels[] = $d->format('d M');
            $data[] = 40 + (int) round(12 * sin($i / 3)) + ($i % 7);
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Kunjungan',
                'data' => $data,
                'borderColor' => 'rgb(16, 185, 129)',
                'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                'fill' => true,
                'tension' => 0.35,
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
            'plugins' => [
                'legend' => ['display' => true],
            ],
        ];
    }
}
