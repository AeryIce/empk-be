<?php

namespace App\Filament\Resources\FoundationResource\RelationManagers;

use App\Models\School;
use App\Services\Harvy\HarvyDapoSyncService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SchoolsRelationManager extends RelationManager
{
    protected static string $relationship = 'schools';

    protected static ?string $title = 'Sekolah di bawah Yayasan ini';

    public function table(Table $table): Table
    {
        return $table
            // Urutkan default: jenjang lalu nama sekolah
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
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('npsn')
                    ->label('NPSN')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('kabkota')
                    ->label('Kota / Kab.')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('paroki')
                    ->label('Paroki')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('Publik?')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ])
            ->headerActions([
                // Untuk sekarang, tidak ada Create di relasi Yayasan (read-only + Harvy)
            ])
            ->actions([
                Tables\Actions\Action::make('harvy_sync')
                    ->label('Harvy: Sync DAPO')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('info')
                    ->modalHeading('Harvy: Bind & Sync DAPO')
                    ->modalDescription('Masukkan URL halaman sekolah di DAPO. Harvy akan mengikat endpoint ini dan mensinkronkan metadata resmi ke registry lokal.')
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
                        // 1. Ambil URL dari form
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

                        // 2. Simpan URL ke model (in-memory dulu, nanti akan ikut ke-save di sync service)
                        $record->dapo_school_url = $dapoUrl;

                        // 3. Coba ekstrak dapo_id dari URL (segment terakhir path)
                        $dapoId = null;
                        $parts  = parse_url($dapoUrl);

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

                        // 4. Jalankan Harvy sync dengan gaya "hacker console", amanin error juga
                        try {
                            /** @var HarvyDapoSyncService $sync */
                            $sync    = app(HarvyDapoSyncService::class);
                            $summary = $sync->syncSchool($record);
                        } catch (\InvalidArgumentException $e) {
                            // Kalau masih ada kasus tanpa endpoint yang valid
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

                        // 5. Kalau DAPO ngambek / status bukan fetched
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

                        // 6. Sukses fetched: tampilkan "console log" gaya hacker
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
                // Tidak kita izinkan bulk delete dari relasi Yayasan
            ]);
    }

    public function getDefaultTableSortColumn(): ?string
    {
        return 'jenjang';
    }

    public function getDefaultTableSortDirection(): ?string
    {
        return 'asc';
    }
}
