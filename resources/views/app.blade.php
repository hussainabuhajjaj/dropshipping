<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Simbazu') }}</title>

        @php
            $site = data_get($page ?? [], 'props.site', []);
            $favicon = data_get($site, 'favicon_path');
            $faviconPath = $favicon ? asset('storage/' . ltrim((string) $favicon, '/')) : null;
        @endphp

        {{-- Dynamic Favicon --}}
        @if($faviconPath)
            <link rel="icon" href="{{ $faviconPath }}" type="image/x-icon">
        @else
            <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='75' font-size='75' font-weight='bold' fill='%232563eb'>A</text></svg>">
        @endif

        <!-- User Preferences -->
        <meta name="current-currency" content="{{ $current_currency ?? 'USD' }}">
        <meta name="current-language" content="{{ $current_language ?? 'en' }}">
        
        <!-- PWA -->
        <meta name="theme-color" content="#0f172a">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Simbazu') }}">
        <meta name="mobile-web-app-capable" content="yes">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/category-default.png') }}">
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700&display=swap" rel="stylesheet" />
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders:opsz,wght@10..72,400..900&display=swap" rel="stylesheet">

        <!-- Scripts -->
        <script src="https://cdnjs.cloudflare.com/polyfill/v3/polyfill.min.js?features=Promise%2Cfetc%2CObject.assign%2CArray.from%2CArray.prototype.includes%2CString.prototype.startsWith%2CString.prototype.endsWith%2CSymbol"></script>
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
        
        <!-- Paystack Configuration -->
        <script>
            window.paystackConfig = {
                korapayEnabled: @json(config('payments.korapay_enabled', true)),
                korapayVisibleOnCheckout: @json(config('payments.korapay_visible_on_checkout', false)),
                paystackEnabled: @json(config('payments.paystack_enabled', true)),
                paystackMobileMoneyEnabled: @json(config('payments.paystack_mobile_money_enabled', true)),
                paystackMobileMoney: @json(config('payments.paystack_mobile_money', [])),
            };
        </script>
        
        <!-- TikTok Pixel Code -->
        @if(config('services.tiktok.pixel_id'))
        <script>
        !function (w, d, t) {
          w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(
        var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script")
        ;n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};


          ttq.load('{{ config("services.tiktok.pixel_id") }}');
          ttq.page();
        }(window, document, 'ttq');
        </script>
        @endif

        <!-- Meta Pixel Code -->
        @if(config('services.facebook.pixel_id'))
        <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '{{ config('services.facebook.pixel_id') }}');
        fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none"
        src="https://www.facebook.com/tr?id={{ config('services.facebook.pixel_id') }}&ev=PageView&noscript=1"
        /></noscript>
        @endif
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
