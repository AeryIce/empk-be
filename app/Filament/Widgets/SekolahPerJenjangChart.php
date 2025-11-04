<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class SekolahPerJenjangChart extends ChartWidget
{
    protected static ?string $heading = 'Sekolah per Jenjang';
    protected static ?int $sort = 12;
    protected static ?string $maxHeight = '300px';
    protected int|string|array $columnSpan = ['lg' => 1];

    protected function getData(): array
    {
        $labels = ['SD', 'SMP', 'SMA', 'SMK', 'SLB'];
        $data   = [64, 52, 41, 33, 4];

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Jumlah',
                'data' => $data,
                'backgroundColor' => 'rgba(59,130,246,0.2)',
                'borderColor' => 'rgb(59,130,246)',
                'borderWidth' => 1.5,
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }
}
