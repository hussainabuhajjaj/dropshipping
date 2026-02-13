<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Livewire\AdminDatabaseNotifications;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use App\Http\Middleware\CheckStorefrontComingSoon;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\Facades\Route;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(config('filament.path', 'admin'))
            ->authGuard(config('filament.auth.guard', 'admin'))
            ->login()
            ->brandName(env('APP_NAME' ).' Admin')
            ->favicon(asset('favicon.ico'))
            ->profile()
            ->colors([
                'primary' => Color::Slate,
            ])
            ->renderHook(
                PanelsRenderHook::SCRIPTS_BEFORE,
                fn (): string => view('filament.partials.support-chat-echo')->render()
            )
            ->databaseNotifications(livewireComponent: AdminDatabaseNotifications::class)
            ->databaseNotificationsPolling('10s')
            ->databaseTransactions() //optional
            ->sidebarCollapsibleOnDesktop()

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->userMenuItems(
                Route::has('profile.edit')
                    ? ['profile' => MenuItem::make()->label('Profile')->url(route('profile.edit'))]
                    : []
            )
            ->middleware($this->baseMiddleware())
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Base middleware stack for the panel.
     *
     * @return array<int, class-string>
     */
    private function baseMiddleware(): array
    {
        return [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            CheckStorefrontComingSoon::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ];
    }
}
