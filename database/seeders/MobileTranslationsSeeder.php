<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MobileTranslation;
use Illuminate\Database\Seeder;

class MobileTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLocale('en');
        $this->seedLocale('fr');
    }

    private function seedLocale(string $locale): void
    {
        $messages = $this->loadJsonLocale($locale);
        if (empty($messages)) {
            return;
        }

        foreach ($messages as $key => $value) {
            if (! is_string($key) || ! is_string($value) || $key === '') {
                continue;
            }

            MobileTranslation::updateOrCreate(
                ['locale' => $locale, 'key' => $key],
                ['value' => $value]
            );
        }
    }

    private function loadJsonLocale(string $locale): array
    {
        $path = resource_path("lang/{$locale}.json");
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }
}
