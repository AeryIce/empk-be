<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SystemStatus;
use App\Filament\Widgets\WelcomeCard;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            WelcomeCard::class,
            SystemStatus::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['sm' => 1, 'lg' => 2, '2xl' => 3];
    }
}
