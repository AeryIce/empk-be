<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsPostResource\Pages;
use App\Models\NewsPost;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class NewsPostResource extends Resource
{
    protected static ?string $model = NewsPost::class;

    protected static bool $shouldCheckPolicyExistence = false;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';
    protected static ?string $navigationGroup = 'Publikasi';
    protected static ?string $navigationLabel = 'Berita & Event';
    protected static ?string $pluralModelLabel = 'Berita & Event';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Konten')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Judul')
                        ->required()
                        ->maxLength(200)
                        ->live(onBlur: true),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->disabled()
                        ->dehydrated()
                        ->helperText('Slug dibuat otomatis dari judul saat pertama kali dibuat.'),

                    Forms\Components\Textarea::make('excerpt')
                        ->label('Ringkasan (untuk preview & SEO)')
                        ->rows(3)
                        ->maxLength(300),

                    Forms\Components\FileUpload::make('cover_path')
                        ->label('Cover')
                        ->disk('cloudinary')
                        ->directory('EMPK/berita/covers')
                        ->visibility('private')
                        ->image()
                        ->maxSize(4096)
                        ->getUploadedFileUsing(function (string $file, ?array $storedFileNames): ?array {
                            if ($file === '') {
                                return null;
                            }

                            // Cloudinary public_id biasanya tanpa ekstensi
                            $publicId = preg_replace('/\.(jpe?g|png|gif|webp|bmp|svg)$/i', '', $file) ?? $file;

                            $name = $storedFileNames[$file] ?? basename($file);

                            return [
                                'name' => $name,
                                'size' => null,
                                'type' => null,
                                'url'  => (string) Cloudinary::image($publicId)->toUrl(),
                            ];
                        })
                        ->helperText('Sekali upload -> otomatis ke Cloudinary.'),

                    Forms\Components\TextInput::make('cover_alt')
                        ->label('Alt text cover (SEO)')
                        ->maxLength(255),

                    Forms\Components\RichEditor::make('body_html')
                        ->label('Isi Berita (bisa sisip foto)')
                        ->fileAttachmentsDisk('cloudinary')
                        ->fileAttachmentsDirectory('EMPK/berita/inline')
                        ->fileAttachmentsVisibility('private')
                        ->getUploadedAttachmentUrlUsing(function (string $file): ?string {
                            if ($file === '') {
                                return null;
                            }

                            $publicId = preg_replace('/\.(jpe?g|png|gif|webp|bmp|svg)$/i', '', $file) ?? $file;

                            return (string) Cloudinary::image($publicId)->toUrl();
                        })
                        ->required(),

                    Forms\Components\TextInput::make('youtube_url')
                        ->label('YouTube URL (opsional)')
                        ->maxLength(255),
                ])
                ->columns(2),

            Forms\Components\Section::make('Klasifikasi & Publikasi')
                ->schema([
                    Forms\Components\TagsInput::make('labels')->label('Label'),
                    Forms\Components\TagsInput::make('categories')->label('Kategori'),
                    Forms\Components\TagsInput::make('tags')->label('Tag'),
                    Forms\Components\TagsInput::make('facts')->label('Fakta singkat'),

                    Forms\Components\DateTimePicker::make('event_start_at')
                        ->label('Mulai Event (opsional)'),

                    Forms\Components\DateTimePicker::make('event_end_at')
                        ->label('Selesai Event (opsional)'),

                    Forms\Components\TextInput::make('location')
                        ->label('Lokasi (opsional)')
                        ->maxLength(255),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'draft' => 'Draft',
                            'published' => 'Published',
                        ])
                        ->default('draft')
                        ->required(),

                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Published at (auto jika kosong)')
                        ->helperText('Kalau status Published dan ini kosong, akan diisi otomatis saat simpan.'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\ImageColumn::make('cover_url')
                ->label('Cover')
                ->circular(),

            Tables\Columns\TextColumn::make('title')
                ->label('Judul')
                ->searchable()
                ->limit(50),

            Tables\Columns\TextColumn::make('status')
                ->badge()
                ->label('Status'),

            Tables\Columns\TextColumn::make('published_at')
                ->label('Published')
                ->dateTime('d M Y H:i'),
        ])
            ->defaultSort('published_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNewsPosts::route('/'),
            'create' => Pages\CreateNewsPost::route('/create'),
            'view'   => Pages\ViewNewsPost::route('/{record}'),
            'edit'   => Pages\EditNewsPost::route('/{record}/edit'),
        ];
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['title'])) {
            $data['slug'] = static::generateUniqueSlug((string) $data['title']);
        }

        if (!isset($data['created_by_user_id']) || !$data['created_by_user_id']) {
            $data['created_by_user_id'] = Auth::id();
        }

        return static::mutatePublishing($data);
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        // slug tetap (biar link publik gak berubah-ubah)
        return static::mutatePublishing($data);
    }

    private static function mutatePublishing(array $data): array
    {
        $status = $data['status'] ?? 'draft';

        if ($status === 'published') {
            if (empty($data['published_at'])) {
                $data['published_at'] = now();
            }
        } else {
            $data['published_at'] = null;
        }

        return $data;
    }

    private static function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base ?: 'post';
        $i = 2;

        while (NewsPost::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
