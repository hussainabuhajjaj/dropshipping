<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $adminPath = trim((string) config('filament.path', 'admin'), '/');
        $adminGuard = (string) config('filament.auth.guard', 'admin');

        Broadcast::routes([
            'middleware' => ['web', 'auth:customer'],
        ]);

        Broadcast::routes([
            'prefix' => $adminPath,
            'middleware' => ['web', 'auth:' . $adminGuard],
        ]);

        Broadcast::routes([
            'prefix' => 'api/mobile/v1',
            'middleware' => ['auth:sanctum'],
        ]);

        require base_path('routes/channels.php');
    }
}
