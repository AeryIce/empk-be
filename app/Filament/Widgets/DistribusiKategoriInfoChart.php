<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class DistribusiKategoriInfoChart extends ChartWidget
{
    protected static ?string $heading = 'Distribusi Kategori Info';
    protected static ?int $sort = 11;
    protected static ?string $maxHeight = '300px';
    protected int|string|array $columnSpan = ['lg' => 1];

    protected function getData(): array
    {
        $labels = ['Pengumuman', 'Agenda', 'Beasiswa', 'Kegiatan', 'Lainnya'];
        $data   = [12, 9, 4, 7, 3];

        return [
            'labels' => $labels,
            'datasets' => [[
                'data' => $data,
                'backgroundColor' => [
                    'rgb(59,130,246)',   // blue-500
                    'rgb(16,185,129)',   // emerald-500
                    'rgb(249,115,22)',   // orange-500
                    'rgb(99,102,241)',   // indigo-500
                    'rgb(244,63,94)',    // rose-500
                ],
                'borderWidth' => 0,
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
