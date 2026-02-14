<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Products\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CjNormalizeCategoryNamesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalizes_legacy_cj_breadcrumb_name(): void
    {
        $category = Category::query()->create([
            'cj_id' => 'A6158EC0-C66D-456D-923C-E784EE432A02',
            'name' => "Women's Clothing > Underwears > Bras [CJ A6158EC0-C66D-456D-923C-E784EE432A02]",
            'slug' => 'legacy',
            'parent_id' => null,
        ]);

        Artisan::call('cj:normalize-category-names');

        $category->refresh();
        $this->assertSame('Bras', $category->name);
        $this->assertStringNotContainsString('[CJ', $category->name);
        $this->assertStringNotContainsString('>', $category->name);
    }

    public function test_it_adds_suffix_when_leaf_name_conflicts_under_same_parent(): void
    {
        Category::query()->create([
            'name' => 'Bras',
            'slug' => 'bras',
            'parent_id' => null,
        ]);

        $legacy = Category::query()->create([
            'cj_id' => 'A6158EC0-C66D-456D-923C-E784EE432A02',
            'name' => "Women's Clothing > Underwears > Bras [CJ A6158EC0-C66D-456D-923C-E784EE432A02]",
            'slug' => 'legacy-2',
            'parent_id' => null,
        ]);

        Artisan::call('cj:normalize-category-names');

        $legacy->refresh();
        $this->assertNotSame('Bras', $legacy->name);
        $this->assertStringContainsString('Bras', $legacy->name);
        $this->assertStringContainsString('(CJ', $legacy->name);
        $this->assertStringNotContainsString('[CJ', $legacy->name);
        $this->assertStringNotContainsString('>', $legacy->name);
    }
}

