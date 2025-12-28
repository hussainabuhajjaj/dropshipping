<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use Filament\Notifications\Notification;
use App\Filament\Pages\BasePage;
use UnitEnum;

class CJIntegration extends BasePage
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cloud';

    protected static UnitEnum|string|null $navigationGroup = 'Integrations';

    protected static ?int $navigationSort = 90;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.cj-integration';

    public ?array $settingsData = null;
    public ?array $productData = null;
    public ?array $variantsData = null;
    public ?array $stockData = null;

    public ?string $accountName = null;
    public ?string $accountEmail = null;
    public ?string $pid = null;
    public ?string $vid = null;

    public function fetchSettings(): void
    {
        try {
            $client = app(CJDropshippingClient::class);
            $resp = $client->getSettings();
            $this->settingsData = $resp->data ?? null;
            Notification::make()->title('Settings fetched')->success()->send();
        } catch (ApiException $e) {
            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function updateAccount(): void
    {
        $this->validate([
            'accountName' => ['nullable', 'string', 'max:200'],
            'accountEmail' => ['nullable', 'email', 'max:200'],
        ]);

        if (! $this->accountName && ! $this->accountEmail) {
            Notification::make()->title('Nothing to update')->warning()->send();
            return;
        }

        try {
            $client = app(CJDropshippingClient::class);
            $resp = $client->updateAccount($this->accountName, $this->accountEmail);
            Notification::make()->title('Account updated')->body($resp->message ?? 'Success')->success()->send();
        } catch (ApiException $e) {
            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function fetchProduct(): void
    {
        $this->validate([
            'pid' => ['required', 'string', 'max:200'],
        ]);

        try {
            $client = app(CJDropshippingClient::class);
            $resp = $client->getProduct($this->pid);
            $this->productData = $resp->data ?? null;
            Notification::make()->title('Product fetched')->success()->send();
        } catch (ApiException $e) {
            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function fetchVariants(): void
    {
        $this->validate([
            'pid' => ['required', 'string', 'max:200'],
        ]);

        try {
            $client = app(CJDropshippingClient::class);
            $resp = $client->getVariantsByPid($this->pid);
            $this->variantsData = $resp->data ?? null;
            Notification::make()->title('Variants fetched')->success()->send();
        } catch (ApiException $e) {
            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function fetchStock(): void
    {
        $this->validate([
            'vid' => ['required', 'string', 'max:200'],
        ]);

        try {
            $client = app(CJDropshippingClient::class);
            $resp = $client->getStockByVid($this->vid);
            $this->stockData = $resp->data ?? null;
            Notification::make()->title('Stock fetched')->success()->send();
        } catch (ApiException $e) {
            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

}

