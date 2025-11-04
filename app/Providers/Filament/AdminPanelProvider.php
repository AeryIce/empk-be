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

            // Inject asset publish-an (CSS/JS) lewat satu view hook.
            // Pastikan file: resources/views/filament/hooks/assets.blade.php
            ->renderHook('panels::head.end', fn () => view('filament.hooks.assets'))

            // Auth & halaman dasar
            ->login()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class, // default Dashboard; widget2 akan tampil di sini
            ])

            // Widgets di dashboard
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                SystemStatus::class,
                WelcomeCard::class,
                AgendaRapatDemo::class,
                OverviewStats::class,
                TrenKunjunganChart::class,
                DistribusiKategoriInfoChart::class,
                SekolahPerJenjangChart::class,
                Widgets\AccountWidget::class,
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
