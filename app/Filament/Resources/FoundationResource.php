<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FoundationResource\Pages;
use App\Models\Foundation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use App\Filament\Resources\FoundationResource\RelationManagers\SchoolsRelationManager;

class FoundationResource extends Resource
{
    protected static ?string $model = Foundation::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Yayasan';

    protected static ?string $pluralModelLabel = 'Yayasan';

    protected static ?string $modelLabel = 'Yayasan';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // IDENTITAS DASAR
                Forms\Components\Section::make('Identitas Yayasan')
                    ->schema([
                        Forms\Components\TextInput::make('kode')
                            ->label('Kode MPK')
                            ->maxLength(50)
                            ->disabled(),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Yayasan')
                            ->required()
                            ->maxLength(150),
                        Forms\Components\TextInput::make('alias')
                            ->label('Alias / Singkatan')
                            ->maxLength(150),
                        Forms\Components\TextInput::make('jenis')
                            ->label('Jenis')
                            ->placeholder('Yayasan / Kongregasi / Lainnya')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug (otomatis)')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('ID di URL, akan digenerate otomatis dari nama.'),
                        Forms\Components\TextInput::make('motto')
                            ->label('Motto')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                // ALAMAT
                Forms\Components\Section::make('Alamat')
                    ->schema([
                        Forms\Components\Textarea::make('alamat')
                            ->label('Alamat')
                            ->rows(2),
                        Forms\Components\TextInput::make('kabkota')
                            ->label('Kota / Kab.')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('provinsi')
                            ->label('Provinsi')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('kode_pos')
                            ->label('Kode Pos')
                            ->maxLength(10),
                    ])
                    ->columns(2),

                // PROFIL & NILAI
                Forms\Components\Section::make('Profil & Nilai Dasar')
                    ->schema([
                        Forms\Components\Textarea::make('about_text')
                            ->label('Tentang Kami')
                            ->rows(4),
                        Forms\Components\Textarea::make('vision_text')
                            ->label('Visi')
                            ->rows(3),
                        Forms\Components\Textarea::make('mission_text')
                            ->label('Misi')
                            ->rows(4),
                        Forms\Components\Textarea::make('core_values')
                            ->label('Nilai Inti')
                            ->rows(3)
                            ->helperText('Misal: Respect – Responsiveness – Responsibility – Creativity'),
                        Forms\Components\Textarea::make('program_unggulan')
                            ->label('Program Unggulan')
                            ->rows(4),
                        Forms\Components\Textarea::make('services_text')
                            ->label('Pelayanan (teks tambahan)')
                            ->rows(3)
                            ->helperText('Keterangan layanan di luar daftar sekolah, jika ada.'),
                    ])
                    ->columns(2),

                // PENGURUS & DOKUMEN
                Forms\Components\Section::make('Pengurus & Dokumen')
                    ->schema([
                        Forms\Components\TextInput::make('chair_name')
                            ->label('Ketua Yayasan')
                            ->maxLength(150),
                        Forms\Components\TextInput::make('secretary_name')
                            ->label('Sekretaris')
                            ->maxLength(150),
                        Forms\Components\TextInput::make('treasurer_name')
                            ->label('Bendahara')
                            ->maxLength(150),
                        Forms\Components\Textarea::make('board_text')
                            ->label('Pengurus Inti Lainnya')
                            ->rows(3),
                        Forms\Components\TextInput::make('brochure_pdf_url')
                            ->label('URL Brosur / Profil PDF')
                            ->maxLength(255)
                            ->url()
                            ->helperText('Bisa isi link Cloudinary / Drive yang sudah dibuka untuk publik.'),
                    ])
                    ->columns(2),

                // KONTAK & MEDIA
                Forms\Components\Section::make('Kontak & Media')
                    ->schema([
                        Forms\Components\TextInput::make('telepon')
                            ->label('Telepon')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('whatsapp')
                            ->label('WhatsApp')
                            ->maxLength(30),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(150),
                        Forms\Components\TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('instagram')
                            ->label('Instagram')
                            ->maxLength(100)
                            ->helperText('Hanya username atau full URL, dua-duanya boleh.'),
                        Forms\Components\TextInput::make('facebook')
                            ->label('Facebook')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('youtube')
                            ->label('YouTube')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('logo_url')
                            ->label('Logo (URL Cloudinary)')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('cover_url')
                            ->label('Cover (URL Cloudinary)')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('profile_image_url')
                            ->label('Foto Profil / Kampus (URL)')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('qr_url')
                            ->label('QR Code (URL gambar)')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                // STATUS & CATATAN
                Forms\Components\Section::make('Status & Catatan')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                        Forms\Components\TagsInput::make('tags')
                            ->label('Tag')
                            ->placeholder('Tambah tag'),
                        Forms\Components\Textarea::make('notes_internal')
                            ->label('Catatan Internal (hanya MPK)')
                            ->rows(3),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kode')
                    ->label('Kode')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Yayasan')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('kabkota')
                    ->label('Kota/Kab.')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('provinsi')
                    ->label('Provinsi')
                    ->sortable(),
                Tables\Columns\TextColumn::make('schools_count')
                    ->label('Sekolah')
                    ->counts('schools')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListFoundations::route('/'),
            'create' => Pages\CreateFoundation::route('/create'),
            'view' => Pages\ViewFoundation::route('/{record}'),
            'edit' => Pages\EditFoundation::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('schools')
            ->orderBy('kode');
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = static::generateSlug($data);

        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['name'])) {
            $data['slug'] = static::generateSlug($data);
        }

        return $data;
    }

    protected static function generateSlug(array $data): string
    {
        $base = $data['name'] ?? 'yayasan';

        if (! empty($data['kode'])) {
            $base .= '-'.$data['kode'];
        }

        return Str::slug($base);
    }
    public static function getRelations(): array
    {
        return [
            SchoolsRelationManager::class,
        ];
    }
}
