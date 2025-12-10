<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            // --- Integrasi DAPO & portal pemerintah ---
            $table->string('dapo_id', 50)->nullable()->after('npsn');
            $table->string('dapo_school_url', 255)->nullable()->after('dapo_id');
            $table->string('referensi_pusdatin_url', 255)->nullable()->after('dapo_school_url');
            $table->string('sekolah_kita_url', 255)->nullable()->after('referensi_pusdatin_url');
            $table->string('rapor_pmp_url', 255)->nullable()->after('sekolah_kita_url');

            $table->timestampTz('dapo_last_sync_at')->nullable()->after('rapor_pmp_url');
            $table->json('dapo_snapshot')->nullable()->after('dapo_last_sync_at');

            // --- Identitas & legal resmi (mirror dari DAPO/Referensi) ---
            $table->string('status_kepemilikan_resmi', 50)->nullable()->after('tipe_penyelenggara');
            $table->string('sk_pendirian_nomor', 100)->nullable()->after('status_kepemilikan_resmi');
            $table->date('sk_pendirian_tanggal')->nullable()->after('sk_pendirian_nomor');
            $table->string('sk_izin_operasional_nomor', 100)->nullable()->after('sk_pendirian_tanggal');
            $table->date('sk_izin_operasional_tanggal')->nullable()->after('sk_izin_operasional_nomor');

            // --- Data operasional pemerintah (Data Rinci DAPO) ---
            $table->string('status_bos', 50)->nullable()->after('status_sekolah'); // Ya / Tidak / -
            $table->string('waktu_penyelenggaraan', 50)->nullable()->after('status_bos'); // Pagi / Siang / Kombinasi / -
            $table->string('sertifikasi_iso', 50)->nullable()->after('waktu_penyelenggaraan'); // ISO 9001:2015 / -

            // --- Listrik, internet, dan sarana teknis ---
            $table->string('sumber_listrik_resmi', 50)->nullable()->after('status_bangunan');
            $table->unsignedInteger('daya_listrik_va')->nullable()->after('sumber_listrik_resmi');
            $table->unsignedInteger('kecepatan_internet_mbps')->nullable()->after('daya_listrik_va');
            $table->unsignedInteger('luas_tanah_m2')->nullable()->after('kecepatan_internet_mbps');
            $table->string('akses_internet_keterangan', 100)->nullable()->after('kecepatan_internet_mbps');

            // --- ABK & data pelengkap (versi pemerintah) ---
            $table->string('kebutuhan_khusus_dilayani', 255)->nullable()->after('menerima_siswa_berkebutuhan_khusus');

            $table->string('nama_bank', 100)->nullable()->after('kebutuhan_khusus_dilayani');
            $table->string('cabang_bank', 100)->nullable()->after('nama_bank');
            $table->string('rekening_atas_nama', 150)->nullable()->after('cabang_bank');

            // --- Geo-lokasi resmi ---
            $table->decimal('lintang', 11, 8)->nullable()->after('kabkota');
            $table->decimal('bujur', 11, 8)->nullable()->after('lintang');

            // --- Kontak operator sekolah ---
            $table->string('operator_sekolah', 150)->nullable()->after('kepala_sekolah');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropColumn([
                'dapo_id',
                'dapo_school_url',
                'referensi_pusdatin_url',
                'sekolah_kita_url',
                'rapor_pmp_url',
                'dapo_last_sync_at',
                'dapo_snapshot',
                'status_kepemilikan_resmi',
                'sk_pendirian_nomor',
                'sk_pendirian_tanggal',
                'sk_izin_operasional_nomor',
                'sk_izin_operasional_tanggal',
                'status_bos',
                'waktu_penyelenggaraan',
                'sertifikasi_iso',
                'sumber_listrik_resmi',
                'daya_listrik_va',
                'kecepatan_internet_mbps',
                'luas_tanah_m2',
                'akses_internet_keterangan',
                'kebutuhan_khusus_dilayani',
                'nama_bank',
                'cabang_bank',
                'rekening_atas_nama',
                'lintang',
                'bujur',
                'operator_sekolah',
            ]);
        });
    }
};
