<?php

namespace App\Console\Commands;

use App\Domain\Products\Models\ProductVariant;
use Illuminate\Console\Command;

class CjNormalizeVariantOptions extends Command
{
    protected $signature = 'cj:normalize-variant-options
        {--dry-run : Show what would change without writing}
        {--chunk=100 : Number of variants to process per chunk}';

    protected $description = 'Normalize CJ variant options by re-parsing from metadata.cj_variant.variantProperty';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        $total = ProductVariant::query()
            ->whereNotNull('options->Option')
            ->count();

        if ($total === 0) {
            $this->warn('No variants with flat Option key found.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} variants with flat Option key.");

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        ProductVariant::query()
            ->whereNotNull('options->Option')
            ->select(['id', 'options', 'metadata', 'cj_variant_data'])
            ->chunk($chunkSize, function ($variants) use ($dryRun, &$updated, &$skipped, &$errors) {
                foreach ($variants as $variant) {
                    try {
                        $result = $this->normalize($variant);
                        if ($result === null) {
                            $skipped++;
                            continue;
                        }

                        if ($dryRun) {
                            $this->line("[DRY-RUN] Variant #{$variant->id}: {$result['description']}");
                            $updated++;
                            continue;
                        }

                        $variant->options = $result['options'];
                        $variant->save();
                        $updated++;
                    } catch (\Throwable $e) {
                        $this->error("Variant #{$variant->id}: " . $e->getMessage());
                        $errors++;
                    }
                }
            });

        $this->line('');
        $this->table(
            ['Status', 'Count'],
            [
                ['Updated', $updated],
                ['Skipped', $skipped],
                ['Errors', $errors],
            ]
        );

        if ($dryRun && $updated > 0) {
            $this->warn('Dry-run mode — no changes were saved. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    private function normalize(ProductVariant $variant): ?array
    {
        $metadata = $variant->metadata ?? [];
        $cjVariant = $metadata['cj_variant'] ?? null;
        $cjVariantData = $variant->cj_variant_data ?? [];

        $raw = $cjVariant['variantProperty'] ?? $cjVariantData['variantProperty'] ?? null;

        if ($raw !== null) {
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $raw = $decoded;
                }
            }

            if (is_array($raw)) {
                $parsed = [];
                foreach ($raw as $entry) {
                    if (! is_array($entry)) continue;
                    $name = $entry['propertyName'] ?? $entry['name'] ?? null;
                    $value = $entry['propertyValue'] ?? $entry['value'] ?? null;
                    if (is_string($name) && $name !== '' && is_string($value) && $value !== '') {
                        $parsed[trim($name)] = trim($value);
                    }
                }
                if ($parsed !== []) {
                    $old = $variant->options['Option'] ?? '?';
                    $desc = implode(', ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($parsed), $parsed));
                    return ['options' => $parsed, 'description' => "{$old} → {$desc}"];
                }
            }
        }

        // Fallback: try to split compound Option value like "White-80cm"
        $currentOptions = $variant->options ?? [];
        $optionValue = $currentOptions['Option'] ?? null;
        if (! is_string($optionValue) || $optionValue === '') {
            return null;
        }

        $parsed = $this->splitCompoundOption($optionValue);
        if ($parsed === null) {
            return null;
        }

        return [
            'options' => $parsed,
            'description' => "{$optionValue} → Color: {$parsed['Color']}, Size: {$parsed['Size']}",
        ];
    }

    private function splitCompoundOption(string $value): ?array
    {
        // We only support "-" separator based on actual CJ data patterns
        if (! str_contains($value, '-')) {
            return null;
        }

        $parts = explode('-', $value);
        $parts = array_map('trim', $parts);
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));

        if (count($parts) < 2) {
            return null;
        }

        // Pattern: Color-SizeNumeric (e.g. "White-80cm", "Black-160cm")
        if (count($parts) === 2) {
            // Check if second part looks like a measurement or shoe size
            if (preg_match('/^\d+(\.\d+)?\s*(cm|mm|m|kg|g|inch|inches)$/i', $parts[1])
                || preg_match('/^\d{2,3}$/', $parts[1])) {
                return ['Color' => $parts[0], 'Size' => $parts[1]];
            }

            // Second part is a letter size
            if (preg_match('/^(xxs|xs|s|m|l|xl|xxl|xxxl|xxxxl|one\s?size|free\s?size)$/i', $parts[1])) {
                return ['Color' => $parts[0], 'Size' => $parts[1]];
            }

            // Generic 2-part: first = Color, second = Size
            return ['Color' => $parts[0], 'Size' => $parts[1]];
        }

        // 3 parts: Color-GarmentType-Size (e.g. "Beige-Pants-S", "Khaki-Top-M")
        // Merge middle part with last part for Size (e.g. "Pants-S")
        return [
            'Color' => $parts[0],
            'Size' => $parts[1] . '-' . $parts[2],
        ];
    }
}
