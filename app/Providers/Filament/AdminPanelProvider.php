<?php

namespace App\Providers\Filament;

use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

// ✅ pakai namespace yang benar
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;

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

            // ✅ Daftarkan asset publish-an ke panel (pakai helper asset() biar aman URL-nya)
            ->assets([
                Css::make('filament-support-css', asset('css/filament/support/support.css')),
                Css::make('filament-forms-css', asset('css/filament/forms/forms.css')),
                Css::make('filament-app-css', asset('css/filament/filament/app.css')),

                Js::make('filament-support-js', asset('js/filament/support/support.js')),
                Js::make('filament-notifications-js', asset('js/filament/notifications/notifications.js')),
                Js::make('filament-tables-js', asset('js/filament/tables/components/table.js')),
                Js::make('filament-app-js', asset('js/filament/filament/app.js')),
            ])

            // Auth & halaman dasar
            ->login()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class, // pakai Dashboard kustom kamu; kalau belum ada ganti ke Pages\Dashboard::class
            ])

            // Widgets
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
