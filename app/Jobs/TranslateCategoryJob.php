<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Products\Models\Category;
use App\Services\AI\CategoryTranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TranslateCategoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<int, string> $locales
     */
    public function __construct(
        private readonly int $categoryId,
        private readonly array $locales,
        private readonly string $sourceLocale = 'en',
        private readonly bool $force = false,
    ) {
    }

    public function handle(CategoryTranslationService $service): void
    {
        $category = Category::query()->find($this->categoryId);
        if (! $category) {
            return;
        }

        $service->translate($category, $this->locales, $this->sourceLocale, $this->force);
    }
}
