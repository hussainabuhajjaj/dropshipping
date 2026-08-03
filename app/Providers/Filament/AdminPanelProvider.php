<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Domain\WooCommerce\Filament\WooCommerceProductImportPage;
use App\Domain\WooCommerce\Filament\WooCommerceSettingsPage;
use App\Domain\WooCommerce\Filament\WooCommerceSyncLogResource;
use App\Domain\WooCommerce\Filament\WooCommerceWebhookLogResource;
use App\Filament\Pages\SupportChatCenter;
use App\Filament\Resources\AffiliateCommissionResource;
use App\Filament\Resources\AffiliateReferralResource;
use App\Filament\Resources\AffiliateResource;
use App\Filament\Resources\AffiliateWithdrawalResource;
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
use App\Http\Middleware\SetFilamentLocale;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\Facades\Route;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel
            ->default()
            ->id('admin')
            ->path(config('filament.path', 'admin'));

        if (config('woocommerce.enabled', false)) {
            $panel
                ->resources([
                    WooCommerceSyncLogResource::class,
                    WooCommerceWebhookLogResource::class,
                ])
                ->pages([
                    WooCommerceSettingsPage::class,
                    WooCommerceProductImportPage::class,
                ])
                ->widgets([
                    \App\Domain\WooCommerce\Filament\WooCommerceSyncStatsWidget::class,
                ]);
        }

        return $panel
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
            ->databaseNotifications()
            ->databaseTransactions() //optional
            ->sidebarCollapsibleOnDesktop()
            ->bootUsing(function () {
                app()->setLocale('en');
                config(['app.locale' => 'en']);
            })

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->resources([
                AffiliateResource::class,
                AffiliateCommissionResource::class,
                AffiliateWithdrawalResource::class,
                AffiliateReferralResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                SupportChatCenter::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->userMenuItems(
                Route::has('profile.edit')
                    ? ['profile' => MenuItem::make()->label('Profile')->url(route('profile.edit'))]
                    : []
            )
            ->middleware([
                ...$this->baseMiddleware(),
                'admin',
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private function baseMiddleware(): array
    {
        return [
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
        ];
    }
}
