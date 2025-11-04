<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Str;

class DaftarInfoDemo extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-list-bullet';
    protected static ?string $navigationLabel = 'Daftar Info (Demo)';
    protected static ?string $navigationGroup = 'Demo';
    protected static ?int    $navigationSort  = 2;

    protected static string $view = 'filament.pages.daftar-info-demo';

    public string $search = '';

    public function getFilteredItemsProperty(): array
    {
        $q = Str::lower($this->search);

        return array_values(array_filter($this->items(), function ($row) use ($q) {
            if ($q === '') return true;

            return Str::contains(Str::lower($row['judul']), $q)
                || Str::contains(Str::lower($row['kategori']), $q)
                || Str::contains(Str::lower($row['ringkasan']), $q);
        }));
    }

    /**
     * Data dummy untuk demo list (belum ambil dari DB).
     * Setelah presentasi disetujui, kita ganti ini jadi Eloquent query.
     */
    protected function items(): array
    {
        return [
            [
                'judul' => 'Pengumuman Rapat Koordinasi',
                'kategori' => 'pengumuman',
                'tanggal_publish' => now()->subDays(2)->format('Y-m-d'),
                'tampilkan' => true,
                'ringkasan' => 'Rapat koordinasi awal semester.',
            ],
            [
                'judul' => 'Agenda Sosialisasi e-MPK',
                'kategori' => 'agenda',
                'tanggal_publish' => now()->addDays(1)->format('Y-m-d'),
                'tampilkan' => true,
                'ringkasan' => 'Sosialisasi fitur dan alur kerja e-MPK.',
            ],
            [
                'judul' => 'Program Beasiswa KAJ 2026',
                'kategori' => 'beasiswa',
                'tanggal_publish' => now()->addWeeks(2)->format('Y-m-d'),
                'tampilkan' => false,
                'ringkasan' => 'Informasi awal program beasiswa.',
            ],
            [
                'judul' => 'Kegiatan Pelatihan Guru',
                'kategori' => 'kegiatan',
                'tanggal_publish' => now()->subWeek()->format('Y-m-d'),
                'tampilkan' => true,
                'ringkasan' => 'Workshop peningkatan kompetensi.',
            ],
            [
                'judul' => 'Info Lainnya',
                'kategori' => 'lainnya',
                'tanggal_publish' => now()->format('Y-m-d'),
                'tampilkan' => false,
                'ringkasan' => 'Miscellaneous update.',
            ],
        ];
    }
}
