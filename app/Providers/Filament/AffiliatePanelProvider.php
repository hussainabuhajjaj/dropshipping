<?php

namespace App\Providers\Filament;

use App\Filament\Affiliate\Pages\Dashboard;
use App\Http\Middleware\SetFilamentLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AffiliatePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('affiliate')
            ->path('affiliate')
            ->login()
            ->colors([
                'primary' => 'rgb(59, 130, 246)',
            ])
            ->authGuard('affiliate')
            ->pages([
                Dashboard::class,
            ])
            ->discoverResources(in: app_path('Filament/Affiliate/Resources'), for: 'App\\Filament\\Affiliate\\Resources')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetFilamentLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->brandName(config('app.name') . ' - Affiliate Portal')
            ->bootUsing(fn () => app()->setLocale('en'));
    }
}
