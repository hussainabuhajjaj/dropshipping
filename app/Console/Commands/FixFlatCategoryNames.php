<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Category;
use App\Domain\Products\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FixFlatCategoryNames extends Command
{
    protected $signature = 'categories:fix-flat {--dry-run}';

    protected $description = 'Convert flat category names (containing >) into proper hierarchies';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        $this->line('🔍 Finding categories with > in their names...');
        
        // Find all categories that have ">" in their name
        $flatCats = Category::where('name', 'like', '%>%')->get();
        
        if ($flatCats->isEmpty()) {
            $this->info('✅ No flat categories found!');
            return Command::SUCCESS;
        }
        
        $this->line("Found {$flatCats->count()} flat categories to fix:\n");
        
        $fixed = 0;
        
        foreach ($flatCats as $flatCat) {
            // Split "Women's Clothing > Tops & Sets > Blouses" into hierarchy
            $parts = array_filter(array_map('trim', explode('>', $flatCat->name)));
            
            if (count($parts) < 2) {
                $this->warn("  ❌ {$flatCat->name} - invalid structure");
                continue;
            }
            
            $this->line("  📁 {$flatCat->name}");
            $this->line("     → Splitting into " . count($parts) . " levels");
            
            // Find or create proper hierarchy
            $parent = null;
            $finalCat = null;
            
            foreach ($parts as $idx => $part) {
                $slug = Str::slug($parent ? "{$parent->slug} {$part}" : $part);
                
                   // Prepare CJ ID for this level (only on final level if it's a leaf)
                   $cjId = $idx === count($parts) - 1 ? $flatCat->cj_id : null;
               
                   // First check if category with this CJ ID already exists
                   if ($cjId) {
                       $existingByCjId = Category::where('cj_id', $cjId)->first();
                       if ($existingByCjId) {
                           $this->line("       ✓ Found existing by CJ ID: {$part}");
                           $parent = $existingByCjId;
                           continue;
                       }
                   }
               
                // Find existing
                $cat = Category::where('name', $part)
                    ->where('parent_id', $parent?->id)
                    ->first();
                
                if (!$cat) {
                    if ($dryRun) {
                           $this->line("       [DRY RUN] Would create: {$part}" . ($cjId ? " [CJ: " . substr($cjId, 0, 8) . "]" : ""));
                    } else {
                        $cat = Category::create([
                            'name' => $part,
                            'slug' => $slug,
                            'parent_id' => $parent?->id,
                               'cj_id' => $cjId,
                        ]);
                           $this->line("       ✓ Created: {$part}" . ($cjId ? " [CJ: " . substr($cjId, 0, 8) . "]" : ""));
                    }
                } else {
                       if ($cjId && !$cat->cj_id) {
                        if (!$dryRun) {
                               $cat->update(['cj_id' => $cjId]);
                        }
                    }
                    $this->line("       ✓ Found: {$part}");
                }
                
                $parent = $cat;
                $finalCat = $cat;
            }
            
            // Reassign products from flat category to final hierarchy cat
            if ($finalCat && !$dryRun) {
                Product::where('category_id', $flatCat->id)
                    ->update(['category_id' => $finalCat->id]);
                $this->line("       ✓ Moved products to proper hierarchy");
            }
            
            // Delete the flat category if all products moved
            if (!$dryRun && Product::where('category_id', $flatCat->id)->count() === 0) {
                $flatCat->delete();
                $this->line("       ✓ Deleted flat category");
            }
            
            $fixed++;
            $this->line("");
        }
        
        if ($dryRun) {
            $this->info("✅ DRY RUN completed - would fix $fixed categories");
            $this->line("Run without --dry-run to apply changes");
        } else {
            $this->info("✅ Fixed $fixed categories");
        }
        
        return Command::SUCCESS;
    }
}
