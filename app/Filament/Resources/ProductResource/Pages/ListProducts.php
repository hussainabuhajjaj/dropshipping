<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Domain\Products\Services\CjProductImportService;
use App\Jobs\ApplyProductMarginChunkJob;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\Api\ApiException;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                                   'syncReviews' => true,
                                   'shipToCountry' => (string)(config('services.cj.ship_to_default') ?? ''),
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
                       if (!$settings) {
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
                ->label(fn() => $this->getCjSyncLabel())
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->disabled()
                ->tooltip(fn() => $this->getCjSyncTooltip()),
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
                    $lookupType = (string)($data['lookup_type'] ?? 'pid');
                    $lookupValue = trim((string)($data['lookup_value'] ?? ''));
                    $importer = app(CjProductImportService::class);

                    try {
                        $product = $importer->importByLookup($lookupType, $lookupValue, [
                            'respectSyncFlag' => false,
                            'defaultSyncEnabled' => true,
                            'syncReviews' => true,
                            'shipToCountry' => (string)(config('services.cj.ship_to_default') ?? ''),
                        ]);

                        if (!$product) {
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
            Actions\Action::make('setMarginFromUi')
                ->label('Set Margin (UI)')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->schema([
                    Select::make('scope')
                        ->label('Scope')
                        ->options([
                            'all' => 'All products',
                            'unpriced' => 'Only products with selling price <= 0',
                            'below_margin' => 'Only products below required margin',
                        ])
                        ->default('unpriced')
                        ->native(false)
                        ->required(),
                    Select::make('margin_preset')
                        ->label('Margin preset')
                        ->options([
                            '20' => '20%',
                            '25' => '25%',
                            '30' => '30%',
                            '35' => '35%',
                            '40' => '40%',
                            '50' => '50%',
                            'custom' => 'Custom',
                        ])
                        ->default('35')
                        ->native(false)
                        ->required(),
                    TextInput::make('margin_percent')
                        ->label('Custom margin %')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(500)
                        ->step('0.01')
                        ->required(fn (callable $get): bool => (string) $get('margin_preset') === 'custom')
                        ->visible(fn (callable $get): bool => (string) $get('margin_preset') === 'custom'),
                    Toggle::make('apply_to_variants')
                        ->label('Apply to variants')
                        ->default(true),
                    Toggle::make('use_low_cost_rule')
                        ->label('Use low-cost margin rule')
                        ->default(true),
                    TextInput::make('low_cost_min')
                        ->label('Low-cost min ($)')
                        ->numeric()
                        ->default(0.01)
                        ->minValue(0)
                        ->step('0.01')
                        ->visible(fn (callable $get): bool => (bool) $get('use_low_cost_rule')),
                    TextInput::make('low_cost_max')
                        ->label('Low-cost max ($)')
                        ->numeric()
                        ->default(1)
                        ->minValue(0.01)
                        ->step('0.01')
                        ->visible(fn (callable $get): bool => (bool) $get('use_low_cost_rule')),
                    TextInput::make('low_cost_margin_percent')
                        ->label('Low-cost margin %')
                        ->numeric()
                        ->default(300)
                        ->minValue(0)
                        ->maxValue(2000)
                        ->step('0.01')
                        ->visible(fn (callable $get): bool => (bool) $get('use_low_cost_rule')),
                ])
                ->action(function (array $data): void {
                    try {
                        $preset = (string) ($data['margin_preset'] ?? '35');
                        $margin = $preset === 'custom'
                            ? (float) ($data['margin_percent'] ?? 0)
                            : (float) $preset;

                        if ($margin < 0) {
                            Notification::make()
                                ->title('Invalid margin')
                                ->body('Margin must be greater than or equal to 0.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $applyVariants = (bool) ($data['apply_to_variants'] ?? true);
                        $useLowCostRule = (bool) ($data['use_low_cost_rule'] ?? true);
                        $lowCostMin = is_numeric($data['low_cost_min'] ?? null) ? (float) $data['low_cost_min'] : 0.01;
                        $lowCostMax = is_numeric($data['low_cost_max'] ?? null) ? (float) $data['low_cost_max'] : 1.0;
                        $lowCostMargin = is_numeric($data['low_cost_margin_percent'] ?? null) ? (float) $data['low_cost_margin_percent'] : 300.0;
                        $scope = (string) ($data['scope'] ?? 'unpriced');

                        if ($useLowCostRule && $lowCostMax < $lowCostMin) {
                            Notification::make()
                                ->title('Invalid low-cost range')
                                ->body('Low-cost max must be greater than or equal to low-cost min.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $query = Product::query()
                            ->select('id')
                            ->whereNotNull('cost_price')
                            ->where('cost_price', '>', 0);

                        if ($scope === 'unpriced') {
                            $query->where(function ($q): void {
                                $q->whereNull('selling_price')->orWhere('selling_price', '<=', 0);
                            });
                        } elseif ($scope === 'below_margin') {
                            $minFactor = (1 + ((float) config('pricing.shipping_buffer_percent', 10) / 100))
                                * (1 + ((float) config('pricing.min_margin_percent', 20) / 100));
                            $query->whereRaw('selling_price < (cost_price * ?)', [$minFactor]);
                        }

                        $total = (clone $query)->count();
                        if ($total === 0) {
                            Notification::make()
                                ->title('No matching products')
                                ->body('No products matched the selected scope.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $queueName = (string) config('pricing.bulk_margin_queue', 'pricing');
                        $jobCount = 0;
                        $dispatchChunkSize = 300;

                        $query->orderBy('id')->chunkById(1000, function ($products) use (
                            &$jobCount,
                            $margin,
                            $applyVariants,
                            $useLowCostRule,
                            $lowCostMin,
                            $lowCostMax,
                            $lowCostMargin,
                            $dispatchChunkSize,
                            $queueName
                        ): void {
                            $ids = $products->pluck('id')->map(fn ($id): int => (int) $id)->all();
                            foreach (array_chunk($ids, $dispatchChunkSize) as $idsChunk) {
                                ApplyProductMarginChunkJob::dispatch(
                                    productIds: $idsChunk,
                                    margin: $margin,
                                    applyVariants: $applyVariants,
                                    useLowCostRule: $useLowCostRule,
                                    lowCostMin: $lowCostMin,
                                    lowCostMax: $lowCostMax,
                                    lowCostMargin: $lowCostMargin,
                                )->onQueue($queueName);
                                $jobCount++;
                            }
                        });

                        Notification::make()
                            ->title('Margin update queued')
                            ->body("Queued {$total} product(s) in {$jobCount} job(s) on '{$queueName}' queue. Check product margin logs and storage/logs/laravel.log for failures.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()
                            ->title('Failed to queue margin update')
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

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 100);

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
        if (!$settings) {
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

        if (!$settings?->cj_last_sync_at) {
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
        $widgets = [];

        $translationWidget = \App\Filament\Resources\ProductResource\Widgets\ProductTranslationProgressWidget::class;
        if (class_exists($translationWidget)) {
            $widgets[] = $translationWidget;
        }

        return $widgets;
    }

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        $healthWidget = \App\Filament\Resources\ProductResource\Widgets\ProductHealthStatsWidget::class;
        if (class_exists($healthWidget)) {
            $widgets[] = $healthWidget;
        }

        $countWidget = \App\Filament\Resources\ProductResource\Widgets\ProductCountWidget::class;
        if (class_exists($countWidget)) {
            $widgets[] = $countWidget;
        }

        return $widgets;
    }
}
