<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RedirectGuestToAgentiDevLogin;
use App\Services\Agenti\AgentImpersonationService;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AgentiPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('agenti')
            ->path('agenti')
            ->login()
            ->passwordReset()
            ->brandName('Portale agenti')
         //   ->brandLogo(asset('images/unicoOAM_banner.png'))
            // Opzionale: imposta un'altezza fissa se ti sembra troppo grande o piccolo
            //   ->brandLogoHeight('3rem')
            // Imposta l'icona del browser (favicon)
        //    ->favicon(asset('images/unicoOAM.png'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Agenti/Resources'), for: 'App\Filament\Agenti\Resources')
            ->discoverPages(in: app_path('Filament/Agenti/Pages'), for: 'App\Filament\Agenti\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Agenti/Widgets'), for: 'App\Filament\Agenti\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->userMenuItems([
                Action::make('leaveImpersonation')
                    ->label('Torna all\'admin')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->url(fn (): string => route('agenti.impersonation.leave'))
                    ->postToUrl()
                    ->visible(fn (): bool => app(AgentImpersonationService::class)->isImpersonating()),
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => view('filament.agenti.impersonation-banner')->render(),
            )
            ->middleware([
                RedirectGuestToAgentiDevLogin::class,
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
