<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\GoogleOAuthService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class GoogleOAuthSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.google-oauth-settings';

    public ?string $clientId = null;
    public ?string $clientSecret = null;
    public ?string $redirectUri = null;
    public bool $isConfigured = false;
    public bool $hasCredentialsFile = false;
    public bool $hasAccessToken = false;
    public bool $hasRefreshToken = false;

    public function mount(): void
    {
        $this->clientId = config('services.google.client_id');
        $this->clientSecret = config('services.google.client_secret') ? '****' . substr(config('services.google.client_secret'), -4) : null;
        $this->redirectUri = config('services.google.redirect');

        $this->hasCredentialsFile = Storage::disk('local')->exists('google/oauth-credentials.json');
        $this->hasAccessToken = Storage::disk('local')->exists('google/oauth-token.json');
        $this->hasRefreshToken = Storage::disk('local')->exists('google/oauth-refresh-token.json');

        $oauthService = app(GoogleOAuthService::class);
        $this->isConfigured = $oauthService->isConfigured();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshTokens')
                ->label('Refresh Access Token')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => $this->hasRefreshToken)
                ->requiresConfirmation()
                ->action(function () {
                    $oauthService = app(GoogleOAuthService::class);

                    if ($oauthService->refreshAccessToken()) {
                        Notification::make()
                            ->title('Token Refreshed')
                            ->success()
                            ->send();

                        $this->mount();
                    } else {
                        Notification::make()
                            ->title('Refresh Failed')
                            ->body('Could not refresh access token. Check logs.')
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('clearTokens')
                ->label('Clear All Tokens')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => $this->hasAccessToken || $this->hasRefreshToken)
                ->requiresConfirmation()
                ->action(function () {
                    $oauthService = app(GoogleOAuthService::class);
                    $oauthService->clearTokens();

                    Notification::make()
                        ->title('Tokens Cleared')
                        ->success()
                        ->send();

                    $this->mount();
                }),

            Action::make('testConnection')
                ->label('Test Calendar API')
                ->icon('heroicon-o-beaker')
                ->visible(fn () => $this->isConfigured)
                ->action(function () {
                    $oauthService = app(GoogleOAuthService::class);
                    $events = $oauthService->getCalendarEvents('primary', 5);

                    if ($events !== null) {
                        Notification::make()
                            ->title('Connection Successful')
                            ->body('Found ' . count($events) . ' upcoming events.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Connection Failed')
                            ->body('Could not fetch calendar events. Check logs.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
