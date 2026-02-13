@php
    $realtimeEnabled = (bool) config('support_chat.realtime.enabled', true);
    $broadcastDriver = (string) config('broadcasting.default', 'log');
    $pusherKey = (string) config('broadcasting.connections.pusher.key', '');
    $pusherCluster = (string) config('broadcasting.connections.pusher.options.cluster', '');
    $pusherWsHost = trim((string) env('PUSHER_WS_HOST', ''));
    $pusherWsPort = (int) env('PUSHER_PORT', 443);
    $pusherWssPort = (int) env('PUSHER_WSS_PORT', 443);
    $pusherForceTls = (bool) config('broadcasting.connections.pusher.options.useTLS', true);
    $adminPath = trim((string) config('filament.path', 'admin'), '/');
    $adminBroadcastAuthPath = trim($adminPath . '/broadcasting/auth', '/');
    $appDebug = (bool) config('app.debug', false);
@endphp

@if ($realtimeEnabled && $broadcastDriver === 'pusher' && $pusherKey !== '')
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js" data-navigate-once></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js" data-navigate-once></script>
    <script data-navigate-once>
        (() => {
            const registerDebugTestListener = () => {
                if (! @js($appDebug) || window.__supportChatEchoTestListenerRegistered || ! window.Echo) {
                    return;
                }

                var channel = window.Echo.channel('my-channel');
                channel.listen('.my-event', function (data) {
                    alert(JSON.stringify(data));
                });

                window.__supportChatEchoTestListenerRegistered = true;
            };

            if (window.__supportChatAdminEchoInitialized) {
                registerDebugTestListener();
                return;
            }

            if (window.Echo && typeof window.Echo.private === 'function') {
                window.__supportChatAdminEchoInitialized = true;
                registerDebugTestListener();
                return;
            }

            const EchoCtorCandidates = [window.Echo, window.LaravelEcho];
            const EchoCtor = EchoCtorCandidates.find((item) => typeof item === 'function');

            if (! EchoCtor || typeof window.Pusher === 'undefined') {
                return;
            }

            const echoConfig = {
                broadcaster: 'pusher',
                key: @js($pusherKey),
                cluster: @js($pusherCluster !== '' ? $pusherCluster : null),
                wsPort: @js($pusherWsPort),
                wssPort: @js($pusherWssPort),
                forceTLS: @js($pusherForceTls),
                enabledTransports: ['ws', 'wss'],
                authEndpoint: @js(url('/' . $adminBroadcastAuthPath)),
                withCredentials: true,
            };

            const wsHost = @js($pusherWsHost !== '' ? $pusherWsHost : null);
            if (wsHost) {
                echoConfig.wsHost = wsHost;
            }

            window.Echo = new EchoCtor(echoConfig);

            window.__supportChatAdminEchoInitialized = true;
            registerDebugTestListener();
        })();
    </script>
@endif
