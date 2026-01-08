<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use App\Services\Api\ApiResponse;
use BackedEnum;
use Filament\Notifications\Notification;
use App\Filament\Pages\BasePage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

class CommandCenter extends BasePage
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-command-line';
    protected static UnitEnum|string|null $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 95;
    protected static bool $adminOnly = true;

    protected string $view = 'filament.pages.command-center';

    public ?string $lastCommand = null;
    public array $lastParameters = [];
    public ?string $lastOutput = null;
    public ?int $lastExitCode = null;
    public ?string $lastRanAt = null;

    public ?string $accountName = null;
    public ?string $accountEmail = null;

    public ?string $productPid = null;
    public ?string $variantsPid = null;
    public ?string $stockVid = null;
    public ?string $variantVid = null;
    public ?string $reviewPid = null;
    public int $reviewPageNum = 1;
    public int $reviewPageSize = 20;
    public ?string $warehouseId = null;

    public int $syncStartPage = 1;
    public int $syncPages = 1;
    public int $syncPageSize = 24;
    public bool $syncQueue = false;

    public int $myStartPage = 1;
    public int $myPageSize = 24;
    public int $myMaxPages = 50;
    public bool $myForceUpdate = false;

    public int $snapshotLimit = 200;

    public bool $cleanupDryRun = true;

    public string $openAction = 'listOrders';
    public string $openPayload = '{}';

    public function runCjToken(): void
    {
        $this->runCommand('cj:token');
    }

    public function runCjSettings(): void
    {
        $this->runCommand('cj:settings');
    }

    public function runCjLogout(): void
    {
        $this->runCommand('cj:logout');
    }

    public function runCjSetAccount(): void
    {
        $this->validate([
            'accountName' => ['nullable', 'string', 'max:200'],
            'accountEmail' => ['nullable', 'email', 'max:200'],
        ]);

        if (! $this->accountName && ! $this->accountEmail) {
            Notification::make()->title('Nothing to update')->warning()->send();
            return;
        }

        $this->runCommand('cj:set-account', array_filter([
            '--name' => $this->accountName,
            '--email' => $this->accountEmail,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function runCjProduct(): void
    {
        $this->validate([
            'productPid' => ['required', 'string', 'max:200'],
        ]);

        $this->runCommand('cj:product', [
            'pid' => $this->productPid,
        ]);
    }

    public function runCjVariants(): void
    {
        $this->validate([
            'variantsPid' => ['required', 'string', 'max:200'],
        ]);

        $this->runCommand('cj:variants', [
            'pid' => $this->variantsPid,
        ]);
    }

    public function runCjVariantStock(): void
    {
        $this->validate([
            'stockVid' => ['required', 'string', 'max:200'],
        ]);

        $this->runCommand('cj:variant-stock', [
            'vid' => $this->stockVid,
        ]);
    }

    public function runCjVariantByVid(): void
    {
        $this->validate([
            'variantVid' => ['required', 'string', 'max:200'],
        ]);

        $client = $this->resolveCjApiClient();
        if (! $client) {
            return;
        }

        $this->runApi('cj:variant-by-vid', [
            'vid' => $this->variantVid,
        ], fn () => $client->getVariantByVid($this->variantVid));
    }

    public function runCjProductReviews(): void
    {
        $this->validate([
            'reviewPid' => ['required', 'string', 'max:200'],
            'reviewPageNum' => ['required', 'integer', 'min:1'],
            'reviewPageSize' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        $client = $this->resolveCjApiClient();
        if (! $client) {
            return;
        }

        $this->runApi('cj:product-reviews', [
            'pid' => $this->reviewPid,
            'pageNum' => $this->reviewPageNum,
            'pageSize' => $this->reviewPageSize,
        ], fn () => $client->getProductReviews($this->reviewPid, $this->reviewPageNum, $this->reviewPageSize));
    }

    public function runCjCategories(): void
    {
        $client = $this->resolveCjApiClient();
        if (! $client) {
            return;
        }

        $this->runApi('cj:categories', [], fn () => $client->listCategories());
    }

    public function runCjGlobalWarehouses(): void
    {
        $client = $this->resolveCjApiClient();
        if (! $client) {
            return;
        }

        $this->runApi('cj:global-warehouses', [], fn () => $client->listGlobalWarehouses());
    }

    public function runCjWarehouseDetail(): void
    {
        $this->validate([
            'warehouseId' => ['required', 'string', 'max:200'],
        ]);

        $client = $this->resolveCjApiClient();
        if (! $client) {
            return;
        }

        $this->runApi('cj:warehouse-detail', [
            'id' => $this->warehouseId,
        ], fn () => $client->getWarehouseDetail($this->warehouseId));
    }

    public function runCjSyncProducts(): void
    {
        $this->validate([
            'syncStartPage' => ['required', 'integer', 'min:1'],
            'syncPages' => ['required', 'integer', 'min:1'],
            'syncPageSize' => ['required', 'integer', 'min:1', 'max:200'],
            'syncQueue' => ['boolean'],
        ]);

        $this->runCommand('cj:sync-products', array_filter([
            '--start-page' => $this->syncStartPage,
            '--pages' => $this->syncPages,
            '--page-size' => $this->syncPageSize,
            '--queue' => $this->syncQueue ?: null,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function runCjSyncMyProducts(): void
    {
        $this->validate([
            'myStartPage' => ['required', 'integer', 'min:1'],
            'myPageSize' => ['required', 'integer', 'min:1', 'max:200'],
            'myMaxPages' => ['required', 'integer', 'min:1', 'max:200'],
            'myForceUpdate' => ['boolean'],
        ]);

        $this->runCommand('cj:sync-my-products', array_filter([
            '--start-page' => $this->myStartPage,
            '--page-size' => $this->myPageSize,
            '--max-pages' => $this->myMaxPages,
            '--force-update' => $this->myForceUpdate ?: null,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function runCjImportSnapshots(): void
    {
        $this->validate([
            'snapshotLimit' => ['required', 'integer', 'min:1', 'max:2000'],
        ]);

        $this->runCommand('cj:import-snapshots', [
            '--limit' => $this->snapshotLimit,
        ]);
    }

    public function runReviewsAutoApprove(): void
    {
        $this->runCommand('reviews:auto-approve');
    }

    public function runCleanupCustomers(): void
    {
        $this->validate([
            'cleanupDryRun' => ['boolean'],
        ]);

        $this->runCommand('data:cleanup-customers', array_filter([
            '--dry-run' => $this->cleanupDryRun ?: null,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function runInspire(): void
    {
        $this->runCommand('inspire');
    }

    public function runCjOpenApiAction(): void
    {
        $this->validate([
            'openAction' => ['required', 'string', 'max:200'],
            'openPayload' => ['nullable', 'string'],
        ]);

        $payload = $this->decodeJsonPayload($this->openPayload);
        if ($payload === null) {
            Notification::make()
                ->title('Invalid JSON')
                ->body('Payload must be valid JSON.')
                ->danger()
                ->send();
            return;
        }

        try {
            $client = CjOpenClient::fromConfig();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('CJ configuration error')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }
        $action = $this->openAction;

        $this->runApi('cj-open:' . $action, $payload, function () use ($client, $action, $payload) {
            return match ($action) {
                'searchProducts' => $client->searchProducts($payload),
                'productDetail' => $client->productDetail($this->requirePayloadValue($payload, ['pid', 'productId'], 'pid')),
                'orderStatus' => $client->orderStatus($payload),
                'orderDetail' => $client->orderDetail($payload),
                'listOrders' => $client->listOrders($payload),
                'getOrderDetail' => $client->getOrderDetail($payload),
                'confirmOrder' => $client->confirmOrder($this->requirePayloadValue($payload, ['orderId', 'orderCode'], 'orderId')),
                'deleteOrder' => $client->deleteOrder($this->requirePayloadValue($payload, ['orderId', 'orderCode'], 'orderId')),
                'changeWarehouse' => $client->changeWarehouse(
                    $this->requirePayloadValue($payload, ['orderCode', 'orderId'], 'orderCode'),
                    $this->requirePayloadValue($payload, ['storageId', 'warehouseId'], 'storageId')
                ),
                'getBalance' => $client->getBalance(),
                'payBalance' => $client->payBalance($this->requirePayloadValue($payload, ['orderId'], 'orderId')),
                'payBalanceV2' => $client->payBalanceV2(
                    $this->requirePayloadValue($payload, ['shipmentOrderId'], 'shipmentOrderId'),
                    $this->requirePayloadValue($payload, ['payId'], 'payId')
                ),
                'addCart' => $client->addCart($this->payloadList($payload, ['cjOrderIdList', 'orderIds'])),
                'addCartConfirm' => $client->addCartConfirm($this->payloadList($payload, ['cjOrderIdList', 'orderIds'])),
                'saveGenerateParentOrder' => $client->saveGenerateParentOrder($this->requirePayloadValue($payload, ['shipmentOrderId'], 'shipmentOrderId')),
                'track' => $client->track($payload),
                'trackInfo' => $client->trackInfo($payload),
                'getTrackInfo' => $client->getTrackInfo($payload),
                'disputeProducts' => $client->disputeProducts($payload),
                'disputeConfirmInfo' => $client->disputeConfirmInfo($payload),
                'createDispute' => $client->createDispute($payload),
                'cancelDispute' => $client->cancelDispute($payload),
                'getDisputeList' => $client->getDisputeList($payload),
                'setWebhook' => $client->setWebhook($payload),
                'warehouseDetail' => $client->warehouseDetail($this->requirePayloadValue($payload, ['id', 'storageId'], 'id')),
                'freightQuote' => $client->freightQuote($payload),
                'freightCalculate' => $client->freightCalculate($payload),
                'freightCalculateTip' => $client->freightCalculateTip($payload),
                'createOrderV2' => $client->createOrderV2($payload),
                'createOrderV3' => $client->createOrderV3($payload),
                'createOrder' => $client->createOrder($payload),
                'uploadWaybillInfo' => $client->uploadWaybillInfo($payload),
                'updateWaybillInfo' => $client->updateWaybillInfo($payload),
                default => throw new \InvalidArgumentException('Unknown action.'),
            };
        });
    }

    private function runCommand(string $command, array $parameters = []): void
    {
        $this->lastCommand = $command;
        $this->lastParameters = $parameters;
        $this->lastRanAt = now()->toDateTimeString();

        try {
            $exitCode = Artisan::call($command, $parameters);
            $output = trim(Artisan::output());

            $this->lastExitCode = $exitCode;
            $this->lastOutput = $output !== '' ? $output : 'Command completed with no output.';

            Notification::make()
                ->title('Command completed')
                ->body($command)
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->lastExitCode = 1;
            $this->lastOutput = $e->getMessage();

            Notification::make()
                ->title('Command failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function runApi(string $label, array $parameters, callable $callback): void
    {
        $this->lastCommand = $label;
        $this->lastParameters = $parameters;
        $this->lastRanAt = now()->toDateTimeString();

        try {
            $result = $callback();
            $this->lastExitCode = 0;
            $this->lastOutput = $this->formatOutput($result);

            Notification::make()
                ->title('Request completed')
                ->body($label)
                ->success()
                ->send();
        } catch (\InvalidArgumentException $e) {
            $this->lastExitCode = 1;
            $this->lastOutput = $e->getMessage();

            Notification::make()
                ->title('Missing payload data')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (ApiException $e) {
            $this->lastExitCode = $e->getCode() ?: 1;
            $this->lastOutput = $e->getMessage();

            Notification::make()
                ->title('CJ error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            $this->lastExitCode = 1;
            $this->lastOutput = $e->getMessage();

            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function formatOutput(mixed $result): string
    {
        if ($result instanceof ApiResponse) {
            $result = $result->raw ?? $result->data;
        }

        if (is_string($result)) {
            return $result;
        }

        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            return $json;
        }

        return (string) $result;
    }

    private function decodeJsonPayload(?string $payload): ?array
    {
        $payload = trim((string) $payload);
        if ($payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function requirePayloadValue(array $payload, array $keys, string $label): string
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        throw new \InvalidArgumentException("Missing {$label} in payload.");
    }

    private function payloadList(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if (is_array($value)) {
                return $value;
            }
            if (is_string($value) && $value !== '') {
                $parts = array_map('trim', explode(',', $value));
                return array_values(array_filter($parts, fn ($item) => $item !== ''));
            }
        }

        throw new \InvalidArgumentException('Missing order IDs in payload.');
    }

    private function resolveCjApiClient(): ?CjApiClient
    {
        try {
            return app(CjApiClient::class);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('CJ configuration error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        return null;
    }
}

