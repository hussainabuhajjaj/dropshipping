<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeployOptimize extends Command
{
    protected $signature = 'deploy:optimize
        {--migrate : Run database migrations}
        {--link-storage : Ensure storage symlink is created}
        {--clear : Clear caches before re-caching}
        {--no-config : Skip config cache}
        {--no-routes : Skip route cache}
        {--no-views : Skip view cache}
        {--no-events : Skip event cache}';

    protected $description = 'Optimize app for production: optional migrations, storage link, clear + cache config/routes/views/events.';

    public function handle(): int
    {
        $this->info('Starting deployment optimization...');

        if ($this->option('migrate')) {
            $this->info('Running migrations...');
            $this->call('migrate', ['--force' => true]);
        }

        if ($this->option('link-storage')) {
            $this->info('Ensuring storage symlink...');
            $this->call('storage:link');
        }

        if ($this->option('clear')) {
            $this->info('Clearing caches...');
            $this->call('config:clear');
            $this->call('route:clear');
            $this->call('view:clear');
            $this->call('event:clear');
        }

        $this->info('Caching artifacts...');

        if (! $this->option('no-config')) {
            $this->call('config:cache');
        }

        if (! $this->option('no-routes')) {
            $this->call('route:cache');
        }

        if (! $this->option('no-views')) {
            $this->call('view:cache');
        }

        if (! $this->option('no-events')) {
            $this->call('event:cache');
        }

        $this->info('Deployment optimization complete.');
        return 0;
    }
}
