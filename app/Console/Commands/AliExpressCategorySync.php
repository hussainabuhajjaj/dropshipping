<?php

namespace App\Console\Commands;

use App\Domain\Products\Services\AliExpressCategorySyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AliExpressCategorySync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ali-express-category-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize AliExpress categories into the local catalog';

    /**
     * Execute the console command.
     */
    public function handle(AliExpressCategorySyncService $service)
    {
        $this->info('Starting AliExpress category sync...');

        $synced = $service->syncCategories();

        $count = is_countable($synced) ? count($synced) : 0;
        $this->info("AliExpress category sync finished ({$count} records).");

        return self::SUCCESS;
    }
}
