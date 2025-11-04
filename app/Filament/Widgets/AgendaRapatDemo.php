<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\Widget;

class AgendaRapatDemo extends Widget
{
    protected static string $view = 'filament.widgets.agenda-rapat-demo';
    protected static ?string $heading = 'Agenda Rapat (Demo)';
    protected static ?int $sort = 0;                      // di atas statistik
    protected int|string|array $columnSpan = ['lg' => 1]; // 1 kolom di ≥lg

    /**
     * @return array{items:array<int,array<string,string>>}
     */
    protected function getViewData(): array
    {
        $now   = now('Asia/Jakarta');
        $h     = $now->diffInHours(Carbon::create(2025, 11, 7, 9, 0, 0, 'Asia/Jakarta'), false);
        $badge = $h > 24 ? 'Segera' : ($h >= 0 ? 'Hari ini' : 'Selesai');
        $badgeColor = $h > 24 ? 'bg-amber-100 text-amber-700'
            : ($h >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700');

        return [
            'items' => [
                [
                    'date'  => '1 Nov 2025 · 19:00',
                    'title' => 'Finalisasi Materi',
                    'where' => 'Online (Zoom)',
                    'badge' => 'Selesai',
                    'badgeColor' => 'bg-gray-100 text-gray-700',
                ],
                [
                    'date'  => '6 Nov 2025 · 20:00',
                    'title' => 'Rehearsal & Code Freeze',
                    'where' => 'Kantor MPK',
                    'badge' => 'Siap',
                    'badgeColor' => 'bg-sky-100 text-sky-700',
                ],
                [
                    'date'  => '7 Nov 2025 · 09:00',
                    'title' => 'Presentasi eMPK',
                    'where' => 'Ruang Rapat MPK',
                    'badge' => $badge,
                    'badgeColor' => $badgeColor,
                ],
            ],
        ];
    }
}
