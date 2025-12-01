<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table): void {
            $table->id();

            // Relasi ke yayasan
            $table->foreignId('foundation_id')
                ->nullable()
                ->constrained('foundations')
                ->nullOnDelete();

            // Kode & identitas resmi
            $table->string('kode_mpk', 50)->nullable();
            $table->string('npsn', 20)->nullable();

            // Identitas utama
            $table->string('nama', 150);
            $table->string('slug', 160)->unique();

            // contoh: TK / SD / SMP / SMA / SMK / SLB / Lainnya
            $table->string('jenjang', 20);

            // contoh: aktif / tidak_aktif / tutup / gabung
            $table->string('status_sekolah', 20)->default('aktif');

            // contoh: yayasan / paroki / kongregasi / keuskupan / lainnya
            $table->string('tipe_penyelenggara', 30)->nullable();

            $table->string('tagline', 255)->nullable();
            $table->text('deskripsi_singkat')->nullable();

            // Lokasi & wilayah
            $table->string('alamat', 255)->nullable();
            $table->string('dusun_jalan', 255)->nullable();
            $table->string('kelurahan', 100)->nullable();
            $table->string('kecamatan', 100)->nullable();
            $table->string('kabkota', 100)->nullable();
            $table->string('provinsi', 100)->nullable();
            $table->string('kode_pos', 10)->nullable();

            // Gerejawi
            $table->string('paroki', 150)->nullable();
            $table->string('dekanat', 100)->nullable();
            $table->string('keuskupan', 100)->nullable();

            // Koordinat (untuk peta & sekolah terdekat)
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('maps_place_id', 255)->nullable();

            // Kontak & kehadiran digital
            $table->string('telepon', 50)->nullable();
            $table->string('telepon_alt', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('instagram', 100)->nullable();
            $table->string('facebook', 100)->nullable();
            $table->string('youtube', 100)->nullable();

            // Akademik & akreditasi
            $table->string('akreditasi', 10)->nullable();
            $table->smallInteger('akreditasi_tahun')->nullable();
            $table->string('kurikulum', 30)->nullable();
            $table->string('program_keahlian', 255)->nullable();
            $table->text('program_khusus')->nullable();

            // Komposisi siswa & guru
            $table->integer('jumlah_siswa')->nullable();
            $table->integer('jumlah_siswa_laki')->nullable();
            $table->integer('jumlah_siswa_perempuan')->nullable();
            $table->integer('jumlah_guru')->nullable();
            $table->integer('jumlah_pegawai')->nullable();
            $table->decimal('rasio_guru_siswa', 5, 2)->nullable();

            // Identitas Katolik & pastoral
            $table->string('kepala_sekolah', 150)->nullable();
            $table->string('pastor_pembina', 150)->nullable();
            $table->string('motto_katolik', 255)->nullable();
            $table->text('program_pastoral')->nullable();
            $table->json('kegiatan_rohani_utama')->nullable();

            // Inklusi & ABK
            $table->boolean('menerima_siswa_berkebutuhan_khusus')->default(false);
            $table->boolean('fasilitas_abk_ringan')->default(false);
            $table->boolean('fasilitas_abk_sedang_berat')->default(false);
            $table->text('catatan_inklusi')->nullable();

            // Beasiswa & dukungan finansial
            $table->boolean('program_beasiswa_internal')->default(false);
            $table->boolean('program_beasiswa_eksternal')->default(false);
            $table->text('catatan_beasiswa')->nullable();

            // Fasilitas fisik
            $table->integer('jumlah_ruang_kelas')->nullable();
            $table->integer('jumlah_lab_ipa')->nullable();
            $table->integer('jumlah_lab_komputer')->nullable();
            $table->integer('jumlah_lab_bahasa')->nullable();
            $table->boolean('perpustakaan')->default(false);
            $table->boolean('lapangan_olahraga')->default(false);
            $table->boolean('kapel')->default(false);
            $table->boolean('aula')->default(false);
            $table->boolean('kantin_sehat')->default(false);
            $table->text('fasilitas_lain')->nullable();

            // Data operasional
            $table->smallInteger('tahun_berdiri')->nullable();
            $table->string('status_bangunan', 30)->nullable(); // milik_sendiri / sewa / hibah / lainnya
            $table->string('jam_buka', 50)->nullable();
            $table->text('catatan_operasional')->nullable();

            // Media & tampilan
            $table->string('logo_url', 255)->nullable();
            $table->string('cover_url', 255)->nullable();
            $table->json('photos')->nullable();
            $table->json('quick_facts')->nullable();

            // Flags & status publikasi
            $table->boolean('is_published')->default(false);
            $table->boolean('tampil_di_peta')->default(true);
            $table->boolean('is_featured')->default(false);

            $table->string('status_verifikasi', 30)->default('draft');
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();

            // Analitik & AI (opsional, tapi disiapkan)
            $table->json('tags')->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('ai_flags')->nullable();

            // Audit
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
