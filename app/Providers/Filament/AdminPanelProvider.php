<?php

namespace App\Providers\Filament;

use AlizHarb\ActivityLog\ActivityLogPlugin;
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')

            ->brandLogo(asset('images/unicoloan.png'))
            // Opzionale: imposta un'altezza fissa se ti sembra troppo grande o piccolo
            //   ->brandLogoHeight('3rem')
            // Imposta l'icona del browser (favicon)
            ->favicon(asset('images/unicoloan.png'))

            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->navigationGroups([
                NavigationGroup::make('Settings')
                    ->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('Manuale Utente')
                    ->url(url('/manuali/utente'), shouldOpenInNewTab: true)
                    ->icon('heroicon-o-book-open')
                    ->group('Settings')
                    ->sort(10),
                NavigationItem::make('Manuale Admin')
                    ->url(url('/manuali/admin'), shouldOpenInNewTab: true)
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->group('Settings')
                    ->sort(11),
                NavigationItem::make('Manuale Tecnico')
                    ->url(url('/manuali/tecnico'), shouldOpenInNewTab: true)
                    ->icon('heroicon-o-code-bracket')
                    ->group('Settings')
                    ->sort(12),
                NavigationItem::make('Manuale Funzioni')
                    ->url(url('/manuali/funzioni'), shouldOpenInNewTab: true)
                    ->icon('heroicon-o-squares-2x2')
                    ->group('Settings')
                    ->sort(13),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // AccountWidget::class,
                // FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugin(
                FilamentSocialitePlugin::make()
                    // (required) Add providers corresponding with providers in `config/services.php`.
                    ->providers([
                        Provider::make('microsoft')
                            ->label('Microsoft')
                            ->icon('fab-microsoft')
                            ->color(Color::hex('#0078D4'))
                            ->outlined(false)
                            ->stateless(false),
                        Provider::make('google')
                            ->label('Google')
                            ->icon('fab-google')
                            ->color(Color::hex('#4285F4'))
                            ->outlined(false)
                            ->stateless(false),
                    ])
                // ->registration(true)
            )
            ->plugin(
                ActivityLogPlugin::make()
                    ->label('Log')
                    ->pluralLabel('Logs')
                    ->navigationGroup('Settings')
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
