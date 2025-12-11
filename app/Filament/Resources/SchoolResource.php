<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolResource\Pages;
use App\Models\School;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use App\Services\Harvy\HarvyDapoSyncService;
use Filament\Notifications\Notification;

class SchoolResource extends Resource
{
    protected static ?string $model = School::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Sekolah';

    protected static ?string $pluralModelLabel = 'Sekolah';

    protected static ?string $modelLabel = 'Sekolah';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ========== 1. Relasi & Identitas Utama ==========
                Forms\Components\Section::make('Relasi & Identitas Utama')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('foundation_id')
                            ->label('Yayasan')
                            ->relationship('foundation', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->nullable()
                            ->helperText('Opsional, tapi dianjurkan untuk menghubungkan sekolah ke yayasan.'),

                        Forms\Components\TextInput::make('kode_mpk')
                            ->label('Kode MPK')
                            ->maxLength(50)
                            ->helperText('Kode internal MPK (jika ada).'),

                        Forms\Components\TextInput::make('nama')
                            ->label('Nama Sekolah')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('jenjang')
                            ->label('Jenjang')
                            ->options([
                                'TK'      => 'TK',
                                'SD'      => 'SD',
                                'SMP'     => 'SMP',
                                'SMA'     => 'SMA',
                                'SMK'     => 'SMK',
                                'SLB'     => 'SLB',
                                'Lainnya' => 'Lainnya',
                            ])
                            ->searchable()
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('status_sekolah')
                            ->label('Status Sekolah')
                            ->options([
                                'aktif'       => 'Aktif',
                                'tidak_aktif' => 'Tidak aktif',
                                'tutup'       => 'Tutup',
                                'gabung'      => 'Gabung',
                            ])
                            ->native(false)
                            ->default('aktif'),

                        Forms\Components\TextInput::make('tipe_penyelenggara')
                            ->label('Tipe Penyelenggara')
                            ->placeholder('yayasan / paroki / kongregasi / keuskupan / lainnya')
                            ->maxLength(30),

                        Forms\Components\TextInput::make('npsn')
                            ->label('NPSN')
                            ->maxLength(20)
                            ->helperText('Jika belum punya NPSN, boleh dikosongkan sementara.'),

                        Forms\Components\TextInput::make('tagline')
                            ->label('Tagline')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('deskripsi_singkat')
                            ->label('Deskripsi Singkat')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                // ========== 2. Lokasi & Gerejawi ==========
                Forms\Components\Section::make('Lokasi, Wilayah & Gerejawi')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('alamat')
                            ->label('Alamat')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('dusun_jalan')
                            ->label('Dusun / Jalan')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('kelurahan')
                            ->label('Kelurahan')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('kecamatan')
                            ->label('Kecamatan')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('kabkota')
                            ->label('Kota / Kab.')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('provinsi')
                            ->label('Provinsi')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('kode_pos')
                            ->label('Kode Pos')
                            ->maxLength(10),

                        Forms\Components\TextInput::make('paroki')
                            ->label('Paroki')
                            ->maxLength(150),

                        Forms\Components\TextInput::make('dekanat')
                            ->label('Dekanat')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('keuskupan')
                            ->label('Keuskupan')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('latitude')
                            ->label('Latitude (internal)')
                            ->numeric()
                            ->step('0.0000001')
                            ->helperText('Koordinat manual untuk peta internal (opsional).'),

                        Forms\Components\TextInput::make('longitude')
                            ->label('Longitude (internal)')
                            ->numeric()
                            ->step('0.0000001'),

                        Forms\Components\TextInput::make('lintang')
                            ->label('Lintang (resmi)')
                            ->numeric()
                            ->step('0.00000001')
                            ->helperText('Koordinat lintang dari DAPO / referensi pemerintah (jika ada).'),

                        Forms\Components\TextInput::make('bujur')
                            ->label('Bujur (resmi)')
                            ->numeric()
                            ->step('0.00000001'),

                        Forms\Components\TextInput::make('maps_place_id')
                            ->label('Maps Place ID')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                // ========== 3. Kontak & Kehadiran Digital ==========
                Forms\Components\Section::make('Kontak & Kehadiran Digital')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('telepon')
                            ->label('Telepon')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('telepon_alt')
                            ->label('Telepon Alternatif')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(150),

                        Forms\Components\TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('whatsapp')
                            ->label('WhatsApp')
                            ->maxLength(30),

                        Forms\Components\TextInput::make('instagram')
                            ->label('Instagram')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('facebook')
                            ->label('Facebook')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('youtube')
                            ->label('YouTube')
                            ->maxLength(100),
                    ]),

                // ========== 4. Akademik & Komposisi Warga Sekolah ==========
                Forms\Components\Section::make('Akademik & Komposisi Warga Sekolah')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('akreditasi')
                            ->label('Akreditasi')
                            ->maxLength(10),

                        Forms\Components\TextInput::make('akreditasi_tahun')
                            ->label('Tahun Akreditasi')
                            ->numeric(),

                        Forms\Components\TextInput::make('kurikulum')
                            ->label('Kurikulum')
                            ->maxLength(30),

                        Forms\Components\TextInput::make('program_keahlian')
                            ->label('Program Keahlian')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('program_khusus')
                            ->label('Program Khusus')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('jumlah_siswa')
                            ->label('Jumlah Siswa')
                            ->numeric(),

                        Forms\Components\TextInput::make('jumlah_siswa_laki')
                            ->label('Siswa Laki-laki')
                            ->numeric(),

                        Forms\Components\TextInput::make('jumlah_siswa_perempuan')
                            ->label('Siswa Perempuan')
                            ->numeric(),

                        Forms\Components\TextInput::make('jumlah_guru')
                            ->label('Jumlah Guru')
                            ->numeric(),

                        Forms\Components\TextInput::make('jumlah_pegawai')
                            ->label('Jumlah Pegawai')
                            ->numeric(),

                        Forms\Components\TextInput::make('rasio_guru_siswa')
                            ->label('Rasio Guru : Siswa')
                            ->numeric()
                            ->step('0.01'),
                    ]),

                // ========== 5. Identitas Katolik, Pastoral & Inklusi ==========
                Forms\Components\Section::make('Identitas Katolik, Pastoral & Inklusi')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('kepala_sekolah')
                            ->label('Kepala Sekolah')
                            ->maxLength(150),

                        Forms\Components\TextInput::make('operator_sekolah')
                            ->label('Operator Sekolah')
                            ->maxLength(150),

                        Forms\Components\TextInput::make('pastor_pembina')
                            ->label('Pastor Pembina')
                            ->maxLength(150),

                        Forms\Components\TextInput::make('motto_katolik')
                            ->label('Motto Katolik')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('program_pastoral')
                            ->label('Program Pastoral')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('kegiatan_rohani_utama')
                            ->label('Kegiatan Rohani Utama')
                            ->suggestions([
                                'Misa harian',
                                'Ibadat Sabda',
                                'Pendalaman Iman',
                                'Retret',
                                'Bina Iman Anak',
                            ])
                            ->helperText('Bisa isi beberapa kegiatan rohani utama.'),

                        Forms\Components\Toggle::make('menerima_siswa_berkebutuhan_khusus')
                            ->label('Menerima Siswa Berkebutuhan Khusus?')
                            ->default(false),

                        Forms\Components\Toggle::make('fasilitas_abk_ringan')
                            ->label('Fasilitas ABK Ringan')
                            ->default(false),

                        Forms\Components\Toggle::make('fasilitas_abk_sedang_berat')
                            ->label('Fasilitas ABK Sedang/Berat')
                            ->default(false),

                        Forms\Components\Textarea::make('catatan_inklusi')
                            ->label('Catatan Inklusi')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('kebutuhan_khusus_dilayani')
                            ->label('Kebutuhan Khusus yang Dilayani (versi pemerintah)')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                // ========== 6. Beasiswa & Fasilitas Fisik ==========
                Forms\Components\Section::make('Beasiswa & Fasilitas Fisik')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Toggle::make('program_beasiswa_internal')
                            ->label('Beasiswa Internal')
                            ->default(false),

                        Forms\Components\Toggle::make('program_beasiswa_eksternal')
                            ->label('Beasiswa Eksternal')
                            ->default(false),

                        Forms\Components\Textarea::make('catatan_beasiswa')
                            ->label('Catatan Beasiswa')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('jumlah_ruang_kelas')
                            ->label('Jumlah Ruang Kelas')
                            ->numeric(),

                        Forms\Components\TextInput::make('jumlah_lab_ipa')
                            ->label('Jumlah Lab IPA')
                            ->numeric(),

                        Forms\Components\TextInput::make('jumlah_lab_komputer')
                            ->label('Jumlah Lab Komputer')
                            ->numeric(),

                        Forms\Components\TextInput::make('jumlah_lab_bahasa')
                            ->label('Jumlah Lab Bahasa')
                            ->numeric(),

                        Forms\Components\Toggle::make('perpustakaan')
                            ->label('Perpustakaan')
                            ->default(false),

                        Forms\Components\Toggle::make('lapangan_olahraga')
                            ->label('Lapangan Olahraga')
                            ->default(false),

                        Forms\Components\Toggle::make('kapel')
                            ->label('Kapel')
                            ->default(false),

                        Forms\Components\Toggle::make('aula')
                            ->label('Aula')
                            ->default(false),

                        Forms\Components\Toggle::make('kantin_sehat')
                            ->label('Kantin Sehat')
                            ->default(false),

                        Forms\Components\Textarea::make('fasilitas_lain')
                            ->label('Fasilitas Lain')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                // ========== 7. Operasional & Infrastruktur ==========
                Forms\Components\Section::make('Operasional & Infrastruktur')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('tahun_berdiri')
                            ->label('Tahun Berdiri')
                            ->numeric(),

                        Forms\Components\TextInput::make('status_bangunan')
                            ->label('Status Bangunan')
                            ->placeholder('milik_sendiri / sewa / hibah / lainnya')
                            ->maxLength(30),

                        Forms\Components\TextInput::make('jam_buka')
                            ->label('Jam Buka')
                            ->maxLength(50),

                        Forms\Components\Textarea::make('catatan_operasional')
                            ->label('Catatan Operasional')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('status_bos')
                            ->label('Status BOS')
                            ->placeholder('Ya / Tidak / -')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('waktu_penyelenggaraan')
                            ->label('Waktu Penyelenggaraan')
                            ->placeholder('Pagi / Siang / Kombinasi / -')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('sertifikasi_iso')
                            ->label('Sertifikasi ISO')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('sumber_listrik_resmi')
                            ->label('Sumber Listrik Resmi')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('daya_listrik_va')
                            ->label('Daya Listrik (VA)')
                            ->numeric(),

                        Forms\Components\TextInput::make('kecepatan_internet_mbps')
                            ->label('Kecepatan Internet (Mbps)')
                            ->numeric(),

                        Forms\Components\TextInput::make('luas_tanah_m2')
                            ->label('Luas Tanah (m²)')
                            ->numeric(),

                        Forms\Components\Textarea::make('akses_internet_keterangan')
                            ->label('Keterangan Akses Internet')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                // ========== 8. Portal Pemerintah & Legalitas ==========
                Forms\Components\Section::make('Portal Pemerintah & Legalitas')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('dapo_id')
                            ->label('DAPO ID')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('dapo_school_url')
                            ->label('DAPO School URL')
                            ->placeholder('https://dapo.kemendikdasmen.go.id/sekolah/...')
                            ->url()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('referensi_pusdatin_url')
                            ->label('Referensi Pusdatin URL')
                            ->url()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('sekolah_kita_url')
                            ->label('Sekolah Kita URL')
                            ->url()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('rapor_pmp_url')
                            ->label('Rapor PMP URL')
                            ->url()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('status_kepemilikan_resmi')
                            ->label('Status Kepemilikan Resmi')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('sk_pendirian_nomor')
                            ->label('No. SK Pendirian')
                            ->maxLength(100),

                        Forms\Components\DatePicker::make('sk_pendirian_tanggal')
                            ->label('Tgl SK Pendirian'),

                        Forms\Components\TextInput::make('sk_izin_operasional_nomor')
                            ->label('No. SK Izin Operasional')
                            ->maxLength(100),

                        Forms\Components\DatePicker::make('sk_izin_operasional_tanggal')
                            ->label('Tgl SK Izin Operasional'),

                        Forms\Components\TextInput::make('nama_bank')
                            ->label('Nama Bank')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('cabang_bank')
                            ->label('Cabang Bank')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('rekening_atas_nama')
                            ->label('Rekening a.n.')
                            ->maxLength(150),
                    ]),

                // ========== 9. Media, Tag & Publikasi ==========
                Forms\Components\Section::make('Media, Tag & Publikasi')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('logo_url')
                            ->label('Logo URL')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('cover_url')
                            ->label('Cover URL')
                            ->maxLength(255),

                        Forms\Components\TagsInput::make('tags')
                            ->label('Tags')
                            ->helperText('Tag umum untuk pencarian & filter.'),

                        Forms\Components\Toggle::make('is_published')
                            ->label('Tampilkan di portal utama?')
                            ->default(false),

                        Forms\Components\Toggle::make('tampil_di_peta')
                            ->label('Tampilkan di peta?')
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false),
                    ]),

                // ========== 10. AI & Harvy Snapshot (Read Only) ==========
                Forms\Components\Section::make('Status AI & Harvy (Read Only)')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Textarea::make('ai_summary')
                            ->label('Ringkasan AI')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Direncanakan diisi otomatis oleh modul AI (tidak untuk diedit manual).'),

                        Forms\Components\Textarea::make('ai_flags')
                            ->label('AI Flags')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Placeholder::make('dapo_last_sync_at')
                            ->label('DAPO Last Sync')
                            ->content(fn (?School $record) => $record?->dapo_last_sync_at?->format('d-m-Y H:i') ?? '-'),

                        Forms\Components\Textarea::make('dapo_snapshot')
                            ->label('DAPO Snapshot (JSON)')
                            ->rows(5)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Snapshot mentah dari Harvy. Ditampilkan hanya untuk debugging, tidak untuk diedit.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(
            fn (Builder $query) => $query
                ->orderBy('jenjang')
                ->orderBy('nama')
        )
        ->recordTitleAttribute('nama')
        ->columns([
            Tables\Columns\TextColumn::make('nama')
                ->label('Nama Sekolah')
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('jenjang')
                ->label('Jenjang')
                ->badge()
                ->sortable(),

            Tables\Columns\TextColumn::make('npsn')
                ->label('NPSN')
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('kabkota')
                ->label('Kota / Kab.')
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('foundation.name')
                ->label('Yayasan')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\IconColumn::make('is_published')
                ->label('Publik?')
                ->boolean(),

            // --- Harvy / DAPO status di list ---
            Tables\Columns\IconColumn::make('dapo_school_url')
                ->label('DAPO')
                ->boolean()
                ->tooltip(fn ($record) => $record->dapo_school_url ?: 'Belum ada URL DAPO'),

            Tables\Columns\TextColumn::make('dapo_last_sync_at')
                ->label('Last Sync')
                ->dateTime('d-m-Y H:i')
                ->since()        // tampil juga dalam format "x minutes ago"
                ->toggleable(isToggledHiddenByDefault: true)
                ->sortable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('jenjang')
                ->label('Jenjang')
                ->options(
                    School::query()
                        ->select('jenjang')
                        ->whereNotNull('jenjang')
                        ->distinct()
                        ->orderBy('jenjang')
                        ->pluck('jenjang', 'jenjang')
                        ->toArray()
                ),

            // Sekolah yang BELUM punya URL DAPO
            Tables\Filters\Filter::make('belum_punya_dapo')
                ->label('Belum punya DAPO URL')
                ->query(fn (Builder $query) => $query->whereNull('dapo_school_url')),

            // Sekolah yang SUDAH punya URL DAPO tapi BELUM pernah disync
            Tables\Filters\Filter::make('belum_sync')
                ->label('Punya DAPO, belum sync')
                ->query(fn (Builder $query) => $query
                    ->whereNotNull('dapo_school_url')
                    ->whereNull('dapo_last_sync_at')
                ),

            // Sekolah yang SUDAH pernah di-sync
            Tables\Filters\Filter::make('sudah_sync')
                ->label('Sudah pernah sync')
                ->query(fn (Builder $query) => $query
                    ->whereNotNull('dapo_last_sync_at')
                ),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),

            Tables\Actions\Action::make('harvy_sync')
                ->label('Harvy: Sync DAPO')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('info')
                ->modalHeading('Harvy: Bind & Sync DAPO')
                ->modalDescription('Masukkan URL halaman sekolah di DAPO. Harvy akan mencoba mengunci endpoint ini dan mensinkronkan metadata resmi ke registry lokal.')
                ->form([
                    Forms\Components\TextInput::make('dapo_school_url')
                        ->label('DAPO School URL')
                        ->placeholder('https://dapo.kemendikdasmen.go.id/sekolah/...')
                        ->required()
                        ->url()
                        ->maxLength(255),
                ])
                ->fillForm(function (School $record): array {
                    return [
                        'dapo_school_url' => $record->dapo_school_url,
                    ];
                })
                ->action(function (School $record, array $data): void {
                    $dapoUrl = trim($data['dapo_school_url'] ?? '');

                    if ($dapoUrl === '') {
                        Notification::make()
                            ->title('Harvy cannot lock on target')
                            ->body(
                                "[HRVY-ERR] Empty DAPO URL received.\n" .
                                "[HRVY] Isi URL DAPO sekolah terlebih dahulu, lalu ulangi recon."
                            )
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->dapo_school_url = $dapoUrl;

                    $dapoId  = null;
                    $parts   = parse_url($dapoUrl);

                    if (! empty($parts['path'])) {
                        $segments = explode('/', trim($parts['path'], '/'));
                        $last     = end($segments);

                        if (is_string($last) && $last !== '') {
                            $dapoId = $last;
                        }
                    }

                    if ($dapoId !== null) {
                        $record->dapo_id = $dapoId;
                    }

                    try {
                        /** @var HarvyDapoSyncService $sync */
                        $sync    = app(HarvyDapoSyncService::class);
                        $summary = $sync->syncSchool($record);
                    } catch (\InvalidArgumentException $e) {
                        $lines   = [];
                        $lines[] = '[HRVY-ERR] Unable to resolve DAPO endpoint.';
                        $lines[] = '[HRVY] Local registry says:';
                        $lines[] = "       • nama : {$record->nama}";
                        $lines[] = "       • id   : {$record->id}";
                        $lines[] = ' ';
                        $lines[] = '[HRVY] Pastikan URL DAPO valid, lalu coba lagi.';

                        Notification::make()
                            ->title('Harvy cannot lock on target')
                            ->body(implode("\n", $lines))
                            ->danger()
                            ->send();

                        return;
                    }

                    $status          = $summary['status']              ?? null;
                    $httpStatus      = $summary['http_status']         ?? null;
                    $attempts        = $summary['attempts']            ?? null;
                    $mappedFieldsCnt = $summary['mapped_fields_count'] ?? null;
                    $appliedCount    = $summary['applied_count']       ?? 0;
                    $applied         = $summary['applied']             ?? [];

                    if ($status !== 'fetched') {
                        $lines   = [];
                        $lines[] = '[HRVY-WARN] DAPO core responded with unstable signal.';
                        $lines[] = '[HRVY] Telemetry snapshot:';
                        $lines[] = "       • status   : {$status}";
                        $lines[] = '       • http     : ' . ($httpStatus ?? 'n/a');
                        $lines[] = '       • attempts : ' . ($attempts ?? 'n/a');
                        $lines[] = ' ';
                        $lines[] = '[HRVY] Suggestion: buka URL DAPO di browser,';
                        $lines[] = '[HRVY] lalu retry recon ketika endpoint sudah stabil.';

                        Notification::make()
                            ->title('Harvy recon failed')
                            ->body(implode("\n", $lines))
                            ->danger()
                            ->send();

                        return;
                    }

                    $lines   = [];
                    $lines[] = '[HRVY] Booting Harvy Neural Recon v0.4-ui';
                    $lines[] = '[HRVY] Target acquired:';
                    $lines[] = "       • nama   : {$record->nama}";
                    $lines[] = "       • id     : {$record->id}";
                    $lines[] = '       • npsn   : ' . ($record->npsn ?? 'n/a');
                    $lines[] = ' ';
                    $lines[] = '[HRVY] Locking DAPO endpoint:';
                    $lines[] = '       • url    : ' . ($record->dapo_school_url ?? 'n/a');
                    if (! empty($record->dapo_id)) {
                        $lines[] = '       • dapoId : ' . $record->dapo_id;
                    }
                    $lines[] = ' ';
                    $lines[] = '[HRVY] Opening secure tunnel to DAPO core...';
                    $lines[] = '       • status   : fetched';
                    $lines[] = '       • http     : ' . ($httpStatus ?? 'n/a');
                    $lines[] = '       • attempts : ' . ($attempts ?? 'n/a');
                    $lines[] = '       • mapped   : ' . ($mappedFieldsCnt ?? 'n/a') . ' fields';
                    $lines[] = ' ';

                    if ($appliedCount > 0 && ! empty($applied)) {
                        $lines[] = '[HRVY] Decrypting payload into local registry...';
                        $lines[] = "       • applied  : {$appliedCount} field(s)";
                        $lines[] = '       • keys     : ' . implode(', ', $applied);
                        $lines[] = ' ';
                        $lines[] = '[HRVY] Sync status: PARTIAL-UPDATE (missing_in_db only).';
                        $lines[] = '[HRVY] Manual review via form sekolah masih disarankan.';
                    } else {
                        $lines[] = '[HRVY] No new fields to inject.';
                        $lines[] = '[HRVY] Local registry is already in sync with DAPO snapshot.';
                    }

                    Notification::make()
                        ->title('Harvy recon complete')
                        ->body(implode("\n", $lines))
                        ->success()
                        ->send();
                }),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
}

    

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSchools::route('/'),
            'create' => Pages\CreateSchool::route('/create'),
            'edit'   => Pages\EditSchool::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('foundation');
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = static::generateSlug($data);

        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['nama'])) {
            $data['slug'] = static::generateSlug($data);
        }

        return $data;
    }

    protected static function generateSlug(array $data): string
    {
        $base = $data['nama'] ?? 'sekolah';

        if (! empty($data['npsn'])) {
            $base .= '-' . $data['npsn'];
        } elseif (! empty($data['jenjang'])) {
            $base .= '-' . $data['jenjang'];
        }

        return Str::slug($base);
    }
}
