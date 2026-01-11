<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Domain\Products\Services\CjProductImportService;
use App\Models\SiteSetting;
use App\Services\Api\ApiException;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('syncCjMyProducts')
                ->label('Sync CJ My Products')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->action('syncCjMyProducts'),
            // New: Sync only listed CJ products
            Actions\Action::make('syncListedCjProducts')
                ->label('Sync Listed CJ Products')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function (): void {
                    $importer = app(CjProductImportService::class);
                    $client = app(\App\Infrastructure\Fulfillment\Clients\CJDropshippingClient::class);
                    $startedAt = microtime(true);

                    Notification::make()
                        ->title('CJ listed sync started')
                        ->body('Syncing only listed CJ products...')
                        ->send();

                    try {
                        $resp = $client->listMyProducts([
                            'pageNum' => 1,
                            'pageSize' => 100,
                        ]);
                        $data = $resp->data ?? [];
                        $list = [];
                        // Normalize response to array of products
                        if (is_array($data)) {
                            if (!empty($data['content']) && is_array($data['content'])) {
                                foreach ($data['content'] as $entry) {
                                    if (is_array($entry) && isset($entry['productList']) && is_array($entry['productList'])) {
                                        $list = array_merge($list, $entry['productList']);
                                    } elseif (is_array($entry)) {
                                        $list[] = $entry;
                                    }
                                }
                            } elseif (!empty($data['productList']) && is_array($data['productList'])) {
                                $list = $data['productList'];
                            } elseif (!empty($data['content']) && is_array($data['content'])) {
                                $list = $data['content'];
                            } else {
                                $numericKeys = array_filter(array_keys($data), 'is_int');
                                if ($numericKeys !== []) {
                                    $list = $data;
                                }
                            }
                        }
                        // Filter for listed products only
                        $listed = array_filter($list, function ($item) {
                            return is_array($item) && !empty($item['listedShopNum']) && (int)$item['listedShopNum'] > 0;
                        });
                        $count = 0;
                        foreach ($listed as $record) {
                            $pid = $record['pid'] ?? $record['productId'] ?? $record['id'] ?? null;
                            if (!$pid) {
                                continue;
                            }
                            try {
                                $product = $importer->importByPid($pid, [
                                    'respectSyncFlag' => false,
                                    'defaultSyncEnabled' => true,
                                    'shipToCountry' => (string) (config('services.cj.ship_to_default') ?? ''),
                                ]);
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('CJ error')
                                    ->body("{$pid}: {$e->getMessage()}")
                                    ->danger()
                                    ->send();
                                continue;
                            }
                            if ($product) {
                                $count++;
                            }
                        }
                        $duration = microtime(true) - $startedAt;
                        $message = $count > 0
                            ? sprintf('Imported %d listed CJ product(s) in %.2fs.', $count, $duration)
                            : 'No listed CJ products were imported.';

                        $settings = \App\Models\SiteSetting::query()->first();
                        if (! $settings) {
                            $settings = \App\Models\SiteSetting::create([]);
                        }

                        $settings->update([
                            'cj_last_sync_at' => now(),
                            'cj_last_sync_summary' => $message,
                        ]);

                        Notification::make()
                            ->title('CJ listed sync complete')
                            ->body($message)
                            ->success()
                            ->send();
                    } catch (ApiException $e) {
                        Notification::make()
                            ->title('CJ error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('cjLastSync')
                ->label(fn () => $this->getCjSyncLabel())
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->disabled()
                ->tooltip(fn () => $this->getCjSyncTooltip()),
            Actions\Action::make('importCj')
                ->label('Import from CJ')
                ->icon('heroicon-o-cloud-arrow-down')
                ->schema([
                    Select::make('lookup_type')
                        ->label('Lookup By')
                        ->options([
                            'pid' => 'PID',
                            'productSku' => 'Product SKU',
                            'variantSku' => 'Variant SKU',
                        ])
                        ->default('pid')
                        ->required()
                        ->native(false),
                    TextInput::make('lookup_value')
                        ->label('CJ Identifier')
                        ->helperText('Enter a PID, product SKU, or variant SKU based on the selection.')
                        ->required()
                        ->maxLength(200),
                ])
                ->action(function (array $data): void {
                    $lookupType = (string) ($data['lookup_type'] ?? 'pid');
                    $lookupValue = trim((string) ($data['lookup_value'] ?? ''));
                    $importer = app(CjProductImportService::class);

                    try {
                        $product = $importer->importByLookup($lookupType, $lookupValue, [
                            'respectSyncFlag' => false,
                            'defaultSyncEnabled' => true,
                            'shipToCountry' => (string) (config('services.cj.ship_to_default') ?? ''),
                        ]);

                        if (! $product) {
                            Notification::make()
                                ->title('CJ product not found')
                                ->danger()
                                ->send();
                            return;
                        }

                        Notification::make()
                            ->title("Imported {$product->name}")
                            ->success()
                            ->send();
                    } catch (ApiException $e) {
                        Notification::make()
                            ->title('CJ error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function syncCjMyProducts(): void
    {
        $importer = app(CjProductImportService::class);
        $startedAt = microtime(true);

        Notification::make()
            ->title('CJ sync started')
            ->body('Syncing CJ My Products...')
            ->send();

        try {
            $summary = $importer->syncMyProducts();
        } catch (ApiException $e) {
            Notification::make()
                ->title('CJ error')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        $duration = microtime(true) - $startedAt;
        $message = sprintf(
            'Queued %d product(s) for import (processed %d) in %.2fs.',
            $summary['queued'] ?? 0,
            $summary['processed'] ?? 0,
            $duration
        );

        $settings = SiteSetting::query()->first();
        if (! $settings) {
            $settings = SiteSetting::create([]);
        }

        $settings->update([
            'cj_last_sync_at' => now(),
            'cj_last_sync_summary' => $message,
        ]);

        Notification::make()
            ->title('CJ sync complete')
            ->body($message)
            ->success()
            ->send();
    }

    protected function getCjSyncLabel(): string
    {
        $settings = SiteSetting::query()->first();

        if (! $settings?->cj_last_sync_at) {
            return 'Last CJ sync: never';
        }

        return 'Last CJ sync: ' . $settings->cj_last_sync_at->toDateTimeString();
    }

    protected function getCjSyncTooltip(): ?string
    {
        $settings = SiteSetting::query()->first();

        return $settings?->cj_last_sync_summary;
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\ProductResource\Widgets\ProductCountWidget::class,
        ];
    }
}
