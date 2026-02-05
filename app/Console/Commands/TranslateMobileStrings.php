<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MobileTranslation;
use App\Services\AI\TranslationProvider;
use Illuminate\Console\Command;

class TranslateMobileStrings extends Command
{
    protected $signature = 'mobile:translations:translate {--from=en} {--to=fr} {--limit=200} {--force}';

    protected $description = 'Translate mobile app strings from one locale to another using the configured translation provider.';

    public function handle(TranslationProvider $translator): int
    {
        $from = (string) ($this->option('from') ?: config('services.translation_source_locale', 'en'));
        $to = (string) ($this->option('to') ?: 'fr');
        $limit = (int) ($this->option('limit') ?: 200);
        $force = (bool) $this->option('force');

        $source = MobileTranslation::query()
            ->where('locale', $from)
            ->orderBy('key')
            ->get(['key', 'value']);

        if ($source->isEmpty()) {
            $this->warn("No source translations found for locale {$from}.");
            return self::SUCCESS;
        }

        $translatedCount = 0;
        foreach ($source as $entry) {
            if ($translatedCount >= $limit) {
                break;
            }

            $key = (string) $entry->key;
            $value = (string) $entry->value;

            $existing = MobileTranslation::query()
                ->where('locale', $to)
                ->where('key', $key)
                ->value('value');

            if (! $force && is_string($existing) && trim($existing) !== '') {
                continue;
            }

            try {
                $translated = $translator->translate($value, $from, $to);
            } catch (\Throwable $e) {
                $this->error("Failed to translate key: {$key}. " . $e->getMessage());
                continue;
            }

            MobileTranslation::updateOrCreate(
                ['locale' => $to, 'key' => $key],
                ['value' => $translated]
            );

            $translatedCount++;
        }

        $this->info("Translated {$translatedCount} strings from {$from} to {$to}.");
        return self::SUCCESS;
    }
}
