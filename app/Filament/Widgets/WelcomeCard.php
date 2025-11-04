<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class WelcomeCard extends Widget
{
    protected static string $view = 'filament.widgets.welcome-card';
    protected static ?int $sort = -1;              // taruh paling atas
    protected int|string|array $columnSpan = 'full'; // full width di dashboard grid

    /**
     * @return array{name:string,today:string,countdown:string}
     */
    protected function getViewData(): array
    {
        $now    = now('Asia/Jakarta');
        // atur jam acara kalau mau (misal 09:00)
        $target = Carbon::create(2025, 11, 7, 9, 0, 0, 'Asia/Jakarta');

        $totalHours = $now->diffInHours($target, false);

        if ($totalHours >= 24) {
            $days  = intdiv($totalHours, 24);
            $hours = $totalHours % 24;
            $countdown = "H-{$days} • {$hours}j";
        } elseif ($totalHours >= 0) {
            $countdown = "Hari ini • {$totalHours}j lagi";
        } else {
            $abs   = abs($totalHours);
            $days  = intdiv($abs, 24);
            $hours = $abs % 24;
            $countdown = "H+{$days} • {$hours}j (lewat)";
        }

        $user = Auth::user(); // ← lebih “IDE-friendly” dibanding helper auth()

        // Kalau mau full Bahasa Indonesia:
        // $now->locale('id');

        return [
            'name'      => $user?->name ?? 'Admin',
            'today'     => $now->isoFormat('dddd, D MMMM Y'),
            'countdown' => $countdown,
        ];
    }
}
