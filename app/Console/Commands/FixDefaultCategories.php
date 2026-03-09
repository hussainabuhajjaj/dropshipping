<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixDefaultCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:fix-default 
                            {--dry-run : Show what would be updated without making changes}
                            {--new-name=Uncategorized : New name for default categories}
                            {--force : Force update even if categories already have proper names}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix categories with DEFAULT-10369 placeholder values';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting category fix for DEFAULT-10369 values...');
        
        $dryRun = $this->option('dry-run');
        $newName = $this->option('new-name');
        $force = $this->option('force');

        try {
            // Find categories with DEFAULT-10369 values
            $categoriesQuery = DB::table('categories')
                ->where(function($query) {
                    $query->where('name', 'DEFAULT-10369')
                          ->orWhere('slug', 'default-10369')
                          ->orWhere('cj_category_id', 'DEFAULT-10369')
                          ->orWhere('description', 'LIKE', '%DEFAULT-10369%')
                          ->orWhere('meta_title', 'LIKE', '%DEFAULT-10369%')
                          ->orWhere('meta_description', 'LIKE', '%DEFAULT-10369%');
                });

            $totalCategories = $categoriesQuery->count();
            
            if ($totalCategories === 0) {
                $this->info('No categories found with DEFAULT-10369 values.');
                return 0;
            }

            $this->info("Found {$totalCategories} categories with DEFAULT-10369 values");

            // Show detailed breakdown
            $this->getCategoryBreakdown();

            // Get the categories to process
            $categories = $categoriesQuery->get();

            $updatedCount = 0;
            $errorCount = 0;

            $progressBar = $this->output->createProgressBar($totalCategories);
            $progressBar->start();

            foreach ($categories as $category) {
                try {
                    $updates = $this->prepareCategoryUpdates($category, $newName);
                    
                    if (empty($updates)) {
                        $progressBar->advance();
                        continue;
                    }

                    if (!$dryRun) {
                        DB::table('categories')
                            ->where('id', $category->id)
                            ->update(array_merge($updates, ['updated_at' => now()]));
                    }

                    $updatedCount++;

                    if ($dryRun) {
                        $this->line("\nCategory {$category->id}: " . json_encode($updates));
                    }

                } catch (\Exception $e) {
                    $errorCount++;
                    $this->line("\nError updating category {$category->id}: " . $e->getMessage());
                    Log::error("Category fix error for category {$category->id}", [
                        'error' => $e->getMessage(),
                        'category_id' => $category->id
                    ]);
                }

                $progressBar->advance();
            }

            $progressBar->finish();

            $this->newLine();
            $this->info('=== Fix Complete ===');
            $this->info("Total categories processed: {$totalCategories}");
            $this->info("Successfully updated: {$updatedCount}");
            $this->info("Errors: {$errorCount}");

            if ($dryRun) {
                $this->warn('This was a dry run. Use --force to apply changes.');
            }

            // Show product associations
            $this->showProductAssociations($newName);

            return 0;

        } catch (\Exception $e) {
            $this->error('Fatal error: ' . $e->getMessage());
            Log::error('Category fix command failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }

    private function getCategoryBreakdown()
    {
        $this->newLine();
        $this->info('=== Category Breakdown ===');

        $breakdown = DB::table('categories')
            ->selectRaw('
                SUM(CASE WHEN name = "DEFAULT-10369" THEN 1 ELSE 0 END) as name_default,
                SUM(CASE WHEN slug = "default-10369" THEN 1 ELSE 0 END) as slug_default,
                SUM(CASE WHEN cj_category_id = "DEFAULT-10369" THEN 1 ELSE 0 END) as cj_default,
                SUM(CASE WHEN description LIKE "%DEFAULT-10369%" THEN 1 ELSE 0 END) as description_default,
                SUM(CASE WHEN meta_title LIKE "%DEFAULT-10369%" THEN 1 ELSE 0 END) as meta_title_default,
                SUM(CASE WHEN meta_description LIKE "%DEFAULT-10369%" THEN 1 ELSE 0 END) as meta_description_default
            ')
            ->where(function($query) {
                $query->where('name', 'DEFAULT-10369')
                      ->orWhere('slug', 'default-10369')
                      ->orWhere('cj_category_id', 'DEFAULT-10369')
                      ->orWhere('description', 'LIKE', '%DEFAULT-10369%')
                      ->orWhere('meta_title', 'LIKE', '%DEFAULT-10369%')
                      ->orWhere('meta_description', 'LIKE', '%DEFAULT-10369%');
            })
            ->first();

        $this->line("Categories with DEFAULT-10369 in name: {$breakdown->name_default}");
        $this->line("Categories with DEFAULT-10369 in slug: {$breakdown->slug_default}");
        $this->line("Categories with DEFAULT-10369 in cj_category_id: {$breakdown->cj_default}");
        $this->line("Categories with DEFAULT-10369 in description: {$breakdown->description_default}");
        $this->line("Categories with DEFAULT-10369 in meta_title: {$breakdown->meta_title_default}");
        $this->line("Categories with DEFAULT-10369 in meta_description: {$breakdown->meta_description_default}");
    }

    private function prepareCategoryUpdates($category, $newName)
    {
        $updates = [];

        // Update name if it's the default
        if ($category->name === 'DEFAULT-10369') {
            $updates['name'] = $newName;
        }

        // Update slug if it's the default
        if ($category->slug === 'default-10369') {
            $updates['slug'] = strtolower(str_replace(' ', '-', $newName));
        }

        // Update cj_category_id if it's the default
        if ($category->cj_category_id === 'DEFAULT-10369') {
            $updates['cj_category_id'] = null;
        }

        // Update description if it contains the default
        if (strpos($category->description ?? '', 'DEFAULT-10369') !== false) {
            $updates['description'] = 'Products that have not been categorized yet';
        }

        // Update meta_title if it contains the default
        if (strpos($category->meta_title ?? '', 'DEFAULT-10369') !== false) {
            $updates['meta_title'] = 'Uncategorized Products';
        }

        // Update meta_description if it contains the default
        if (strpos($category->meta_description ?? '', 'DEFAULT-10369') !== false) {
            $updates['meta_description'] = 'Browse products that have not been assigned to a specific category yet.';
        }

        return $updates;
    }

    private function showProductAssociations($newName)
    {
        $this->newLine();
        $this->info('=== Product Associations ===');

        $productCounts = DB::table('categories as c')
            ->leftJoin('product_category as pc', 'c.id', '=', 'pc.category_id')
            ->leftJoin('products as p', 'pc.product_id', '=', 'p.id')
            ->selectRaw('
                c.id,
                c.name,
                c.slug,
                COUNT(p.id) as product_count
            ')
            ->where(function($query) {
                $query->where('c.name', 'DEFAULT-10369')
                      ->orWhere('c.slug', 'default-10369')
                      ->orWhere('c.cj_category_id', 'DEFAULT-10369');
            })
            ->groupBy('c.id', 'c.name', 'c.slug')
            ->orderBy('product_count', 'desc')
            ->get();

        if ($productCounts->isEmpty()) {
            $this->info('No product associations found for default categories.');
            return;
        }

        $this->line('Categories with product associations:');
        foreach ($productCounts as $category) {
            $this->line("  Category {$category->id} ({$category->name}): {$category->product_count} products");
        }

        $totalProducts = $productCounts->sum('product_count');
        $this->info("Total products affected: {$totalProducts}");
    }
}
