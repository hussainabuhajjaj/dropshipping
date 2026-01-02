<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Models\AliExpressToken;
use App\Domain\Products\Services\AliExpressProductImportService;
use App\Domain\Products\Services\AliExpressCategorySyncService;
use Illuminate\Contracts\Support\Htmlable;
use BackedEnum;
use UnitEnum;

class AliExpressImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'AliExpress Import';
    protected static UnitEnum|string|null $navigationGroup = 'Integrations';
    protected static ?int $navigationSort = 50;
    protected static bool $shouldRegisterNavigation = true;
    protected string $view = 'filament.pages.aliexpress-import';

    public static function canAccess(): bool
    {
        return true; // Allow all authenticated admin users to access
    }

    public function getTitle(): string | Htmlable
    {
        return 'AliExpress Integration';
    }

    public function authenticateWithAliExpress(): void
    {
        try {
            redirect(route('aliexpress.oauth.redirect'));
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Authentication Error')
                ->body('Failed to redirect to AliExpress: ' . $e->getMessage())
                ->send();
        }
    }

    public function syncCategories(): void
    {
        try {
            $token = AliExpressToken::getLatestToken();
            if (!$token) {
                Notification::make()
                    ->warning()
                    ->title('Not Authenticated')
                    ->body('Please authenticate with AliExpress first')
                    ->send();
                return;
            }

            if ($token->isExpired()) {
                Notification::make()
                    ->warning()
                    ->title('Token Expired')
                    ->body('Your AliExpress token has expired. Please re-authenticate.')
                    ->send();
                return;
            }

            $service = app(AliExpressCategorySyncService::class);
            $categories = $service->syncCategories();

            Notification::make()
                ->success()
                ->title('Categories Synced ✓')
                ->body('Successfully synced ' . count($categories) . ' categories from AliExpress')
                ->persistent()
                ->send();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Category sync failed', ['error' => $e->getMessage()]);
            Notification::make()
                ->danger()
                ->title('Sync Failed ✗')
                ->body('Error: ' . $e->getMessage())
                ->persistent()
                ->send();
        }
    }

    public function importProducts(): void
    {
        try {
            $token = AliExpressToken::getLatestToken();
            if (!$token) {
                Notification::make()
                    ->warning()
                    ->title('Not Authenticated')
                    ->body('Please authenticate with AliExpress first')
                    ->send();
                return;
            }

            if ($token->isExpired()) {
                Notification::make()
                    ->warning()
                    ->title('Token Expired')
                    ->body('Your AliExpress token has expired. Please re-authenticate.')
                    ->send();
                return;
            }

            Notification::make()
                ->info()
                ->title('Importing Products...')
                ->body('This may take a few moments')
                ->send();

            $service = app(AliExpressProductImportService::class);
            $products = $service->importBySearch([
                'keyword' => 'electronics',
                'page_size' => 20,
            ]);

            Notification::make()
                ->success()
                ->title('Products Imported ✓')
                ->body('Successfully imported ' . count($products) . ' products from AliExpress')
                ->persistent()
                ->send();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Product import failed', ['error' => $e->getMessage()]);
            Notification::make()
                ->danger()
                ->title('Import Failed ✗')
                ->body('Error: ' . $e->getMessage())
                ->persistent()
                ->send();
        }
    }

    public function refreshToken(): void
    {
        try {
            $token = AliExpressToken::getLatestToken();
            if (!$token) {
                Notification::make()
                    ->warning()
                    ->title('No Token')
                    ->body('Please authenticate first')
                    ->send();
                return;
            }

            if (!$token->canRefresh()) {
                Notification::make()
                    ->warning()
                    ->title('Cannot Refresh')
                    ->body('Refresh token expired. Please re-authenticate.')
                    ->send();
                return;
            }

            $response = \Illuminate\Support\Facades\Http::asForm()->post(
                'https://api-sg.aliexpress.com/rest/auth/token/create',
                [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $token->refresh_token,
                    'client_id' => config('ali_express.client_id'),
                    'client_secret' => config('ali_express.client_secret'),
                ]
            );

            $data = $response->json();
            if (!isset($data['access_token'])) {
                throw new \Exception($data['message'] ?? 'Unknown error from AliExpress');
            }

            $token->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
                'expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
                'raw' => json_encode($data),
            ]);

            Notification::make()
                ->success()
                ->title('Token Refreshed ✓')
                ->body('Your AliExpress token has been renewed')
                ->send();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Token refresh failed', ['error' => $e->getMessage()]);
            Notification::make()
                ->danger()
                ->title('Refresh Failed ✗')
                ->body('Error: ' . $e->getMessage())
                ->send();
        }
    }

    public function getToken(): ?AliExpressToken
    {
        try {
            return AliExpressToken::getLatestToken();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Could not fetch AliExpress token', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
