<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Services\CjProductImportService;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CjSyncProductsV2 extends Command
{
    protected $signature = 'cj:sync-products-v2 {--category= : Category ID to filter by} {--page=1 : Starting page} {--size=50 : Page size (max 100)} {--limit= : Maximum number of products to import}';

    protected $description = 'Sync products from CJ using V2 API with proper category hierarchy';

    public function handle(): int
    {
        $this->line('🔄 Starting CJ Product Sync V2...');

        try {
            $client = new CJDropshippingClient();
            $importService = new CjProductImportService($client, app('App\Domain\Products\Services\CjProductMediaService'));

            $categoryId = $this->option('category');
            $page = (int) $this->option('page');
            $size = (int) $this->option('size');
            $limit = $this->option('limit') ? (int) $this->option('limit') : null;

            // Validate page size
            $size = min(max($size, 1), 100);

            $this->info("Configuration:");
            $this->line("  Page: $page");
            $this->line("  Size: $size");
            if ($categoryId) {
                $this->line("  Category ID: $categoryId");
            }
            if ($limit) {
                $this->line("  Limit: $limit products");
            }

            $filters = [
                'pageNum' => $page,
                'pageSize' => $size,
            ];

            if ($categoryId) {
                $filters['categoryId'] = $categoryId;
            }

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $errors = 0;
            $totalProcessed = 0;

            $progressBar = $this->output->createProgressBar();
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');

            do {
                $this->line("\n📦 Fetching page $page with $size products...");

                $filters['pageNum'] = $page;
                   $response = $client->products()->listProductsV2($filters);

                   if (!$response->ok) {
                    $this->error("❌ API Error: " . $response->message);
                    Log::error('CJ V2 API Error', [
                        'message' => $response->message,
                        'code' => $response->code,
                        'page' => $page,
                    ]);
                    break;
                }

                $data = $response->data ?? [];
                $content = $data['content'] ?? [];

                if (empty($content)) {
                    $this->info("✅ No more products to import");
                    break;
                }

                foreach ($content as $item) {
                    $productList = $item['productList'] ?? [];

                    foreach ($productList as $productData) {
                        $totalProcessed++;

                        if ($limit && $totalProcessed > $limit) {
                            $this->line("\n⏸️  Reached import limit of $limit products");
                            break 2;
                        }

                        try {
                            $progressBar->setMessage("Processing product {$totalProcessed}...");
                            $progressBar->advance();

                            $product = $importService->importFromPayload($productData, [], [
                                'updateExisting' => true,
                                'defaultSyncEnabled' => true,
                            ]);

                            if ($product) {
                                // Check if it was newly created or updated
                                $wasRecentlyCreated = $product->wasRecentlyCreated ?? false;
                                
                                if ($wasRecentlyCreated) {
                                    $imported++;
                                } else {
                                    $updated++;
                                }

                                $this->logProductImport($product, $productData);
                            } else {
                                $skipped++;
                            }
                        } catch (\Exception $e) {
                            $errors++;
                            Log::error('Failed to import product', [
                                'pid' => $productData['id'] ?? 'unknown',
                                'error' => $e->getMessage(),
                            ]);
                            $this->warn("⚠️  Error importing product: " . $e->getMessage());
                        }
                    }
                }

                $totalPages = $data['totalPages'] ?? 0;
                $currentPage = $data['pageNumber'] ?? $page;
                $page++;

                if ($page > $totalPages) {
                    break;
                }

            } while (true);

            $progressBar->finish();

            $this->newLine(2);
            $this->info("✅ Sync Complete!");
            $this->line("");
            $this->table(['Metric', 'Count'], [
                ['Imported', $imported],
                ['Updated', $updated],
                ['Skipped', $skipped],
                ['Errors', $errors],
                ['Total Processed', $totalProcessed],
            ]);

            if ($errors > 0) {
                $this->warn("\n⚠️  Some products failed to import. Check logs for details.");
                return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Fatal Error: " . $e->getMessage());
            Log::error('CJ Sync Products V2 Fatal Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }

    private function logProductImport($product, array $productData): void
    {
        $categoryPath = sprintf(
            "%s > %s > %s",
            $productData['oneCategoryName'] ?? 'N/A',
            $productData['twoCategoryName'] ?? 'N/A',
            $productData['threeCategoryName'] ?? 'N/A'
        );

        Log::info('Product imported from CJ V2', [
            'cj_pid' => $product->cj_pid,
            'name' => $product->name,
            'category_id' => $product->category_id,
            'category_path' => $categoryPath,
            'price' => $product->selling_price,
        ]);
    }
}
