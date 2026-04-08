<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\BroadcastServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\QueueServiceProvider;
use App\Providers\Filament\AffiliatePanelProvider;

return Application::configure(basePath: dirname(__DIR__))
        ->withProviders([
        AppServiceProvider::class,
        AdminPanelProvider::class,
        AuthServiceProvider::class,
        BroadcastServiceProvider::class,
        EventServiceProvider::class,
        HorizonServiceProvider::class,
        QueueServiceProvider::class,
        \App\Providers\Filament\AffiliatePanelProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/cj',
            'webhooks/cj/order-status',
            'webhooks/payments/*',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\TrackStorefrontVisit::class,
            \App\Http\Middleware\CheckStorefrontComingSoon::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\ResolveAffiliateReferral::class,
            \App\Http\Middleware\SetUserPreferences::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->group('admin', [
            \App\Http\Middleware\AdminCurrencyMiddleware::class,
        ]);

        $middleware->api(append: [
            \Illuminate\Session\Middleware\StartSession::class,
            \App\Http\Middleware\ApiSetLocale::class,
        ]);

        $middleware->alias([
            'mobile.email.verified' => \App\Http\Middleware\EnsureMobileEmailVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $exception, $request) {
            if ($request->is('api/mobile/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'errors' => null,
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $exception, $request) {
            if ($request->is('api/mobile/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden.',
                    'errors' => null,
                ], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $exception, $request) {
            if ($request->is('api/mobile/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found',
                    'errors' => null,
                ], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception, $request) {
            if ($request->is('api/mobile/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found',
                    'errors' => null,
                ], 404);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $exception, $request) {
            if ($request->is('api/mobile/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage() ?: 'Validation failed',
                    'errors' => $exception->errors(),
                ], $exception->status);
            }
        });
    })->create();
