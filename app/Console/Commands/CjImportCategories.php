<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Services\CjCategorySyncService;
use Illuminate\Console\Command;

class CjImportCategories extends Command
{
    protected $signature = 'cj:import-categories';
    protected $description = 'Import CJ categories into local database (idempotent).';

    public function handle(): int
    {
        $service = app(CjCategorySyncService::class);

        $this->info('🔄 Importing CJ categories...');

        try {
            $result = $service->syncCategoryTree();

            $this->info(sprintf(
                '✅ Completed: synced=%d created=%d updated=%d errors=%d',
                (int) ($result['synced'] ?? 0),
                (int) ($result['created'] ?? 0),
                (int) ($result['updated'] ?? 0),
                (int) ($result['errors'] ?? 0),
            ));

            $levels = $result['levels'] ?? null;
            if (is_array($levels)) {
                $this->line(sprintf(
                    'Levels: L1=%d L2=%d L3=%d',
                    (int) ($levels['level1'] ?? 0),
                    (int) ($levels['level2'] ?? 0),
                    (int) ($levels['level3'] ?? 0),
                ));
            }

            return ((int) ($result['errors'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}

