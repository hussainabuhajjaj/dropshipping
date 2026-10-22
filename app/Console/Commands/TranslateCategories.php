<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Category;
use App\Jobs\TranslateCategoryJob;
use App\Services\AI\CategoryTranslationService;
use Illuminate\Console\Command;

class TranslateCategories extends Command
{
    protected $signature = 'categories:translate
        {--locales=en,fr : Comma-separated locales}
        {--source=en : Source locale}
        {--force : Re-translate even if translations exist}
        {--queue : Dispatch translation jobs to the queue}';

    protected $description = 'Translate category text fields with DeepSeek.';

    public function handle(CategoryTranslationService $service): int
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

        Category::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(50, function ($categories) use ($locales, $source, $force, $queue, $service, &$count) {
                foreach ($categories as $category) {
                    if ($queue) {
                        TranslateCategoryJob::dispatch((int) $category->id, $locales, $source, $force);
                    } else {
                        $full = Category::query()->find($category->id);
                        if ($full) {
                            $service->translate($full, $locales, $source, $force);
                        }
                    }
                    $count++;
                }
            });

        $this->info("Queued/translated {$count} category(s).");

        return self::SUCCESS;
    }
}
