<?php

namespace App\Providers\Filament;

use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Widgets;

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// === Custom Widgets ===
use App\Filament\Widgets\SystemStatus;
use App\Filament\Widgets\OverviewStats;
use App\Filament\Widgets\WelcomeCard;
use App\Filament\Widgets\AgendaRapatDemo;
use App\Filament\Widgets\TrenKunjunganChart;
use App\Filament\Widgets\DistribusiKategoriInfoChart;
use App\Filament\Widgets\SekolahPerJenjangChart;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')

            // Branding & tema
            ->brandName(
                trim('eMPK KAJ Admin ' . (env('APP_CODENAME') ? '· ' . env('APP_CODENAME') : ''))
            )
            ->colors(['primary' => Color::Emerald])
            ->darkMode(true)
            // ->brandLogo(asset('logo-empk.svg'))   // aktifkan setelah file siap
            // ->favicon(asset('favicon.ico'))       // aktifkan setelah file siap
            // ->sidebarCollapsibleOnDesktop()

            // Auth & halaman dasar
            ->login()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])

            // Widget registry (satu kali, urut)
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Top section
                SystemStatus::class,
                WelcomeCard::class,
                AgendaRapatDemo::class,

                // Angka ringkas
                OverviewStats::class,

                // Charts “cetar”
                TrenKunjunganChart::class,
                DistribusiKategoriInfoChart::class,
                SekolahPerJenjangChart::class,

                // Built-in
                Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class, // opsional
            ])

            // Middleware
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
