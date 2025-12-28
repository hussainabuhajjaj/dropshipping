<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Services\CjCategorySyncService;
use Illuminate\Console\Command;

class CjSyncCategories extends Command
{
    protected $signature = 'cj:sync-categories';
    protected $description = 'Sync CJ category tree into local database with full hierarchy';

    public function handle(): int
    {
        $service = app(CjCategorySyncService::class);
        
        $this->info('🔄 Syncing CJ categories...');
        
        try {
            $result = $service->syncCategoryTree();
            
            $this->info("✅ {$result['message']}");
            if ($result['errors'] > 0) {
                $this->warn("⚠️  {$result['errors']} errors during sync");
            }
            
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
