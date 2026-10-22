<?php

return [
    'path' => env('FILAMENT_PATH', 'admin'),
    'domain' => env('FILAMENT_DOMAIN', null),
    'auth' => [
        'guard' => env('FILAMENT_AUTH_GUARD', 'admin'),
        'pages' => [
            'login' => \Filament\Http\Livewire\Auth\Login::class,
        ],
    ],
    'middleware' => [
        'base' => [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
        ],
        'auth' => [
            \Filament\Http\Middleware\Authenticate::class,
        ],
    ],
    'panel' => [
        \App\Providers\Filament\AdminPanelProvider::class,
    ],
];
