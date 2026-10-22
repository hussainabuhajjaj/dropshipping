<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\TranslateProductJob;
use App\Models\Product;
use App\Services\AI\ProductTranslationService;
use Illuminate\Console\Command;

class TranslateProducts extends Command
{
    protected $signature = 'products:translate
        {--locales=en,fr : Comma-separated locales}
        {--source=en : Source locale}
        {--force : Re-translate even if translations exist}
        {--queue : Dispatch translation jobs to the queue}';

    protected $description = 'Translate product names and descriptions with DeepSeek.';

    public function handle(ProductTranslationService $service): int
    {
        $locales = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('locales')))));
        $source = (string) $this->option('source');
        $force = (bool) $this->option('force');
        $queue = (bool) $this->option('queue');

        if ($locales === []) {
            $this->error('No locales provided.');
            return self::FAILURE;
        }

        $count = 0;

        Product::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(50, function ($products) use ($locales, $source, $force, $queue, $service, &$count) {
                foreach ($products as $product) {
                    if ($queue) {
                        TranslateProductJob::dispatch((int) $product->id, $locales, $source, $force);
                    } else {
                        $full = Product::query()->find($product->id);
                        if ($full) {
                            $service->translate($full, $locales, $source, $force);
                        }
                    }
                    $count++;
                }
            });

        $this->info("Queued/translated {$count} product(s).");

        return self::SUCCESS;
    }
}
