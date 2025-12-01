<?php

namespace Database\Seeders;

use App\Models\Foundation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EmpkFoundationNameFixSeeder extends Seeder
{
    /**
     * Jalankan seeder untuk mengupdate nama yayasan
     * berdasarkan file mpk_foundations_master.csv
     */
    public function run(): void
    {
        $path = database_path('seeders/data/mpk_foundations_master.csv');

        if (! file_exists($path)) {
            $this->command->error("File CSV tidak ditemukan: {$path}");

            return;
        }

        $this->command->info("Mulai update nama yayasan dari: {$path}");

        if (($handle = fopen($path, 'r')) === false) {
            $this->command->error('Gagal membuka file CSV.');

            return;
        }

        // Baca header
        $header = fgetcsv($handle);
        if ($header === false) {
            $this->command->error('File CSV kosong atau header tidak terbaca.');
            fclose($handle);

            return;
        }

        $indexes = array_flip($header);

        if (! isset($indexes['ID'], $indexes['Nama'])) {
            $this->command->error("Kolom 'ID' atau 'Nama' tidak ditemukan di header CSV.");
            fclose($handle);

            return;
        }

        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $idRaw = $row[$indexes['ID']] ?? null;
            $namaRaw = $row[$indexes['Nama']] ?? null;

            if ($idRaw === null || $namaRaw === null) {
                $skipped++;
                continue;
            }

            $kode = trim((string) $idRaw);
            $nama = trim((string) $namaRaw);

            if ($kode === '' || $nama === '') {
                $skipped++;
                continue;
            }

            /** @var \App\Models\Foundation|null $foundation */
            $foundation = Foundation::where('kode', $kode)->first();

            if (! $foundation) {
                $this->command->warn("Yayasan dengan kode '{$kode}' tidak ditemukan di tabel foundations.");
                $skipped++;
                continue;
            }

            $foundation->name = $nama;

            // Kalau slug masih default (yayasan-45, dst) bisa kita update
            if (str_starts_with($foundation->slug ?? '', 'yayasan-')) {
                $foundation->slug = Str::slug($nama.'-'.$kode);
            }

            $foundation->save();
            $updated++;
        }

        fclose($handle);

        $this->command->info("Update selesai. Updated: {$updated}, skipped: {$skipped}.");
    }
}
