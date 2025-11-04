<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverviewStats extends BaseWidget
{
    protected ?string $heading = 'Statistik Sekilas';
    protected int|string|array $columnSpan = ['lg' => 2, '2xl' => 2];

    /**
     * @return array<int, \Filament\Widgets\StatsOverviewWidget\Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make('Total Yayasan', '72')
                ->description('Dummy untuk demo')
                ->color('success')
                ->chart([42,45,47,49,52,55,57,60,62,72]),

            Stat::make('Total Sekolah', '214')
                ->description('Dummy untuk demo')
                ->color('info')
                ->chart([150,160,168,172,180,188,195,203,208,214]),

            Stat::make('Total Peserta', '4,350')
                ->description('Dummy untuk demo')
                ->color('warning')
                ->chart([2100,2400,2600,2900,3100,3300,3600,3900,4100,4350]),
        ];
    }
}
