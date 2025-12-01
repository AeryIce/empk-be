<?php

namespace App\Filament\Resources\FoundationResource\RelationManagers;

use App\Models\School;
use Filament\Forms;
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
            // Atur urutan default via query resmi Filament
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
                // Untuk saat ini, kita biarkan read-only dari sisi Yayasan.
                // Nanti kalau mau, bisa diaktifkan:
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
