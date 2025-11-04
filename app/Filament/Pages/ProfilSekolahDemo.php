<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;

class ProfilSekolahDemo extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Profil Sekolah (Demo)';
    protected static ?string $navigationGroup = 'Demo';
    protected static ?int    $navigationSort  = 3;

    protected static string $view = 'filament.pages.profil-sekolah-demo';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'status' => 'swasta_katolik',
            'tampilkan_publik' => true,
            'jenjang' => 'sma',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Sekolah')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('nama_yayasan')
                            ->label('Nama Yayasan')
                            ->required()
                            ->maxLength(150),
                        Forms\Components\TextInput::make('nama_sekolah')
                            ->label('Nama Sekolah')
                            ->required()
                            ->maxLength(150),
                        Forms\Components\TextInput::make('npsn')
                            ->label('NPSN')
                            ->maxLength(20),
                        Forms\Components\Select::make('jenjang')
                            ->label('Jenjang')
                            ->options([
                                'sd'  => 'SD',
                                'smp' => 'SMP',
                                'sma' => 'SMA',
                                'smk' => 'SMK',
                                'slb' => 'SLB',
                            ])
                            ->native(false)
                            ->required(),
                        Forms\Components\TextInput::make('kepala_sekolah')
                            ->label('Kepala Sekolah')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('jumlah_siswa')
                            ->label('Jumlah Siswa')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ]),

                Forms\Components\Section::make('Kontak & Lokasi')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('alamat')
                            ->label('Alamat')
                            ->columnSpanFull()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('kota')
                            ->label('Kota/Kabupaten')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('provinsi')
                            ->label('Provinsi')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('telepon')
                            ->label('Telepon')
                            ->tel()
                            ->maxLength(30),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->maxLength(150),
                    ]),

                Forms\Components\Section::make('Pengaturan Publikasi')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status Sekolah')
                            ->options([
                                'negeri'         => 'Negeri',
                                'swasta_katolik' => 'Swasta Katolik',
                                'swasta_lain'    => 'Swasta Non-Katolik',
                            ])
                            ->native(false)
                            ->required(),
                        Forms\Components\Toggle::make('tampilkan_publik')
                            ->label('Tampilkan Profil di Halaman Publik')
                            ->inline(false)
                            ->default(true),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        // MOCK-ONLY: tidak menyimpan ke DB
        Notification::make()
            ->title('Profil disimpan (DEMO)')
            ->body('Ini hanya simulasi untuk presentasi. Data belum disimpan ke database.')
            ->success()
            ->send();
    }
}
