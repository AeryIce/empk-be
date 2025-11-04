<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\{
    SystemStatus,
    WelcomeCard,
    AgendaRapatDemo,
    OverviewStats,
    TrenKunjunganChart,
    DistribusiKategoriInfoChart,
    SekolahPerJenjangChart
};

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            WelcomeCard::class,           // full width banner
            SystemStatus::class,          // 1 kolom
            AgendaRapatDemo::class,       // 1 kolom
            OverviewStats::class,         // 2 kolom
            TrenKunjunganChart::class,    // 2 kolom
            SekolahPerJenjangChart::class,// 1 kolom
            DistribusiKategoriInfoChart::class, // 1 kolom
        ];
    }

    public function getColumns(): int|array
    {
        // 1 kolom (≤sm), 2 kolom (≥lg), 3 kolom (≥2xl)
        return ['sm' => 1, 'lg' => 2, '2xl' => 3];
    }
}
