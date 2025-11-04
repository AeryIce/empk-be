<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;

class PostInfoDemo extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-newspaper';
    protected static ?string $navigationLabel = 'Post Info (Demo)';
    protected static ?string $navigationGroup = 'Demo';
    protected static ?int    $navigationSort  = 1;

    protected static string $view = 'filament.pages.post-info-demo';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('judul')
                    ->label('Judul Info')
                    ->required()
                    ->maxLength(120)
                    ->placeholder('Contoh: Pengumuman Rapat Koordinasi'),

                Forms\Components\Textarea::make('isi')
                    ->label('Isi/Penjelasan')
                    ->rows(6)
                    ->required()
                    ->placeholder('Ringkasan singkat untuk publik...'),

                Forms\Components\Select::make('kategori')
                    ->label('Kategori')
                    ->options([
                        'pengumuman' => 'Pengumuman',
                        'agenda'     => 'Agenda',
                        'beasiswa'   => 'Beasiswa',
                        'kegiatan'   => 'Kegiatan',
                        'lainnya'    => 'Lainnya',
                    ])
                    ->required()
                    ->native(false),

                Forms\Components\DatePicker::make('tanggal_publish')
                    ->label('Tanggal Publish')
                    ->required(),

                Forms\Components\Toggle::make('tampilkan_di_beranda')
                    ->label('Tampilkan di Beranda')
                    ->inline(false)
                    ->default(true),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        // MOCK-ONLY: tidak menyimpan ke DB
        Notification::make()
            ->title('Simulasi berhasil')
            ->body('Form diterima (DEMO). Data tidak disimpan ke database.')
            ->success()
            ->send();
    }
}
