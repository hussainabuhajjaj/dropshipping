<?php

namespace App\Providers;

use App\Support\Filament\AdminPanelNotification;
use App\Filament\Livewire\AdminDatabaseNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use App\Models\Product;
use App\Models\User;
use App\Observers\ProductSeoObserver;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;

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

        $this->app->bind(FilamentNotification::class, function ($app, array $parameters) {
            $id = (string) ($parameters['id'] ?? Str::orderedUuid());

            return new AdminPanelNotification($id);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerProductionCommandGuard();

        Gate::define('viewApiDocs', function (User $user) {
            return in_array($user->email, ['admin@admin.com']);
        });
        Scramble::configure()
            ->routes(function (Route $route) {
                return Str::startsWith($route->uri, 'api/');
            });
        Vite::prefetch(concurrency: 3);
        Product::observe(ProductSeoObserver::class);
        $this->registerFilamentWidgetAliases();
    }

    private function registerProductionCommandGuard(): void
    {
        if (! app()->runningInConsole() || ! app()->isProduction()) {
            return;
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $command = strtolower(trim((string) $event->command));
            $name = Str::before($command, ' ');

            $blocked = [
                'db:wipe',
                'migrate:fresh',
                'migrate:refresh',
                'migrate:reset',
                'schema:drop',
            ];

            if (! in_array($name, $blocked, true)) {
                return;
            }

            $event->output->writeln('<error>Blocked destructive command in production: ' . $name . '</error>');

            throw new \RuntimeException('Destructive artisan command blocked in production: ' . $name);
        });
    }

    private function registerFilamentWidgetAliases(): void
    {
        $aliases = [
            'app.filament.resources.product-resource.widgets.product-health-stats-widget'
                => \App\Filament\Resources\ProductResource\Widgets\ProductHealthStatsWidget::class,
            'app.filament.resources.product-resource.widgets.product-count-widget'
                => \App\Filament\Resources\ProductResource\Widgets\ProductCountWidget::class,
            'app.filament.livewire.admin-database-notifications'
                => AdminDatabaseNotifications::class,
        ];

        foreach ($aliases as $alias => $class) {
            if (class_exists($class)) {
                Livewire::component($alias, $class);
            }
        }
    }
}
