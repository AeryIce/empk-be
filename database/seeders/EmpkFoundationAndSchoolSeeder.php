<?php

namespace Database\Seeders;

use App\Models\Foundation;
use App\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmpkFoundationAndSchoolSeeder extends Seeder
{
    /**
     * Jalankan seeder.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/mpk_schools_master.csv');

        if (! file_exists($path)) {
            $this->command->error("File CSV tidak ditemukan: {$path}");

            return;
        }

        $this->command->info("Mulai import dari: {$path}");

        $handle = fopen($path, 'r');

        if ($handle === false) {
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

        // Map nama kolom → index
        $indexes = array_flip($header);

        $requiredColumns = ['Yayasan', 'NPSN', 'Nama', 'Jenjang', 'Kecamatan', 'Kabupaten', 'Provinsi'];

        foreach ($requiredColumns as $col) {
            if (! array_key_exists($col, $indexes)) {
                $this->command->error("Kolom '{$col}' tidak ditemukan di header CSV.");
                fclose($handle);

                return;
            }
        }

        $foundationCache = [];
        $rowNumber = 1;
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($handle, $indexes, &$foundationCache, &$rowNumber, &$imported, &$skipped): void {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                $foundationCodeRaw = $row[$indexes['Yayasan']] ?? '';
                $npsnRaw = $row[$indexes['NPSN']] ?? '';
                $namaRaw = $row[$indexes['Nama']] ?? '';
                $jenjangRaw = $row[$indexes['Jenjang']] ?? '';
                $kecamatanRaw = $row[$indexes['Kecamatan']] ?? '';
                $kabupatenRaw = $row[$indexes['Kabupaten']] ?? '';
                $provinsiRaw = $row[$indexes['Provinsi']] ?? '';

                $nama = trim($namaRaw);

                if ($nama === '') {
                    $skipped++;
                    continue; // baris kosong / tidak ada nama sekolah
                }

                // Bersihkan kode yayasan (boleh angka/teks, kita trim saja)
                $foundationCode = trim((string) $foundationCodeRaw);

                // Bersihkan NPSN: ambil digit saja (buang spasi & karakter lain)
                $npsn = preg_replace('/\D+/', '', (string) $npsnRaw);
                $npsn = $npsn !== '' ? $npsn : null;

                $jenjang = trim((string) $jenjangRaw);
                $kecamatan = trim((string) $kecamatanRaw);
                $kabupaten = trim((string) $kabupatenRaw);
                $provinsi = trim((string) $provinsiRaw);

                // --- Foundation (Yayasan) ---

                $foundationId = null;

                if ($foundationCode !== '') {
                    if (! isset($foundationCache[$foundationCode])) {
                        $foundation = Foundation::firstOrCreate(
                            ['kode' => $foundationCode],
                            [
                                'name' => 'Yayasan '.$foundationCode,
                                'slug' => Str::slug('yayasan-'.$foundationCode),
                                'is_active' => true,
                            ]
                        );

                        $foundationCache[$foundationCode] = $foundation->id;
                    }

                    $foundationId = $foundationCache[$foundationCode];
                }

                // --- Slug sekolah ---

                $slugBase = $nama;
                if ($npsn !== null) {
                    $slugBase .= '-'.$npsn;
                } elseif ($foundationCode !== '') {
                    $slugBase .= '-'.$foundationCode;
                }

                $slugCandidate = Str::slug($slugBase) ?: Str::slug($nama).'-'.uniqid();
                $slug = $slugCandidate;
                $counter = 1;

                // Pastikan slug unik
                while (School::where('slug', $slug)->exists()) {
                    $slug = $slugCandidate.'-'.$counter;
                    $counter++;
                }

                // --- Insert School ---

                School::create([
                    'foundation_id' => $foundationId,
                    'kode_mpk' => $foundationCode !== '' ? $foundationCode : null,
                    'npsn' => $npsn,
                    'nama' => $nama,
                    'slug' => $slug,
                    'jenjang' => $jenjang !== '' ? $jenjang : 'Lainnya',

                    'kecamatan' => $kecamatan,
                    'kabkota' => $kabupaten,
                    'provinsi' => $provinsi,

                    // default flags
                    'is_published' => false,
                    'tampil_di_peta' => true,
                    'is_featured' => false,
                    'status_verifikasi' => 'draft',
                ]);

                $imported++;
            }
        });

        fclose($handle);

        $this->command->info("Import selesai. Imported: {$imported}, skipped (tanpa nama sekolah): {$skipped}.");
    }
}
