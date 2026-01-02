<?php

namespace App\Providers;

use App\Models\Product;
use App\Observers\ProductSeoObserver;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind translation provider based on config
        $provider = env('TRANSLATION_PROVIDER', 'libre_translate');

        match ($provider) {
            'deepseek' => $this->app->bind(
                \App\Services\AI\TranslationProvider::class,
                \App\Services\AI\DeepSeekClient::class
            ),
            'libre_translate' => $this->app->bind(
                \App\Services\AI\TranslationProvider::class,
                \App\Services\AI\LibreTranslateClient::class
            ),
            default => $this->app->bind(
                \App\Services\AI\TranslationProvider::class,
                \App\Services\AI\LibreTranslateClient::class
            ),
        };
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Product::observe(ProductSeoObserver::class);
    }
}
