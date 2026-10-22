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
@endphp

@if ($realtimeEnabled && $broadcastDriver === 'pusher' && $pusherKey !== '')
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js" data-navigate-once></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js" data-navigate-once></script>
    <script data-navigate-once>
        (() => {
            const emitRealtimeStatus = (status, meta = {}) => {
                window.__supportChatAdminRealtimeStatus = status;
                window.dispatchEvent(new CustomEvent('support-chat-admin-realtime-status', {
                    detail: { status, ...meta },
                }));
            };

            if (window.__supportChatAdminEchoInitialized) {
                emitRealtimeStatus(window.__supportChatAdminRealtimeStatus ?? 'connected');
                return;
            }

            const EchoCtorCandidates = [window.LaravelEcho, window.Echo];
            const EchoCtor =
                EchoCtorCandidates.find((item) => typeof item === 'function')
                ?? (window.Echo && typeof window.Echo.constructor === 'function' ? window.Echo.constructor : null);

            if (! EchoCtor || typeof window.Pusher === 'undefined') {
                emitRealtimeStatus('unavailable');
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

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
                auth: {
                    headers: {
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                },
                disableStats: true,
            };

            const wsHost = @js($pusherWsHost !== '' ? $pusherWsHost : null);
            if (wsHost) {
                echoConfig.wsHost = wsHost;
            }

            if (window.Echo && typeof window.Echo.disconnect === 'function') {
                try {
                    window.Echo.disconnect();
                } catch (error) {
                    console.warn('Support chat: failed to disconnect existing Echo instance', error);
                }
            }

            emitRealtimeStatus('connecting');
            window.Echo = new EchoCtor(echoConfig);
            const pusherConnection = window.Echo?.connector?.pusher?.connection;

            const mapPusherState = (state) => {
                switch (state) {
                    case 'connected':
                        return 'connected';
                    case 'connecting':
                    case 'initialized':
                        return 'connecting';
                    case 'unavailable':
                        return 'unavailable';
                    case 'failed':
                    case 'disconnected':
                        return 'disconnected';
                    default:
                        return 'connecting';
                }
            };

            if (pusherConnection && typeof pusherConnection.bind === 'function') {
                emitRealtimeStatus(mapPusherState(pusherConnection.state));

                pusherConnection.bind('state_change', (states) => {
                    emitRealtimeStatus(mapPusherState(states?.current), {
                        previous: states?.previous ?? null,
                    });
                });

                pusherConnection.bind('error', (error) => {
                    emitRealtimeStatus('error', { error });
                });
            }

            if (window.Echo && typeof window.Echo.private === 'function') {
                const adminChannel = window.Echo.private('support.admin');
                if (adminChannel && typeof adminChannel.subscribed === 'function') {
                    adminChannel.subscribed(() => emitRealtimeStatus('connected'));
                }
                if (adminChannel && typeof adminChannel.error === 'function') {
                    adminChannel.error((error) => emitRealtimeStatus('auth_error', { error }));
                }
            }

            window.__supportChatAdminEchoInitialized = true;
        })();
    </script>
@endif
