<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <style>
        img { max-width: 100%; height: auto; display: block; }
        a { color: #0ea5e9; }
        .prose img { border-radius: 10px; }
    </style>
</head>
@php
    $brand = config('app.name');
    $accent = '#0ea5e9';
@endphp
<body style="margin:0; padding:0; background:#eef2f7; color:#0f172a;">
    <span style="display:none !important; visibility:hidden; opacity:0; color:transparent; height:0; width:0;">
        {{ $preheader ?? '' }}
    </span>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px;">
                    <tr>
                        <td style="padding:0 6px 14px 6px; font-family:Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size:20px; font-weight:800; color:#0f172a;">
                                        {{ $brand }}
                                    </td>
                                    <td align="right" style="font-size:12px; color:#64748b;">
                                        Style picks • Fast shipping
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; overflow:hidden;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:20px 24px; background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color:#ffffff;">
                                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; letter-spacing:.08em; text-transform:uppercase; opacity:.8;">
                                            New in store
                                        </div>
                                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:22px; font-weight:800; margin-top:6px;">
                                            {{ $title ?? 'Curated deals for you' }}
                                        </div>
                                        <div style="font-family:Arial, Helvetica, sans-serif; font-size:13px; opacity:.85; margin-top:6px;">
                                            Discover drops, bundles, and limited offers selected for your cart.
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:22px 24px; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:1.7;">
                                        <div>
                                            {!! $bodyHtml ?? '' !!}
                                        </div>
                                        @if(!empty($actionUrl))
                                            <div style="margin:20px 0 4px 0;">
                                                <a href="{{ $actionUrl }}" style="display:inline-block; background:{{ $accent }}; color:#ffffff; text-decoration:none; font-weight:700; padding:12px 18px; border-radius:10px;">
                                                    {{ $actionLabel ?? 'Shop now' }}
                                                </a>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 24px; background:#f8fafc; border-top:1px solid #e5e7eb; font-family:Arial, Helvetica, sans-serif;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="font-size:12px; color:#64748b;">
                                                    Need help? Reach us at {{ config('mail.from.address', 'support@example.com') }}.
                                                </td>
                                                <td align="right" style="font-size:12px; color:#64748b;">
                                                    {{ $brand }} • Trusted shopping
                                                </td>
                                            </tr>
                                        </table>
                                        <div style="margin-top:8px; font-size:11px; color:#94a3b8;">
                                            You received this email because you subscribed on {{ $brand }}.
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 8px 0 8px; font-family:Arial, Helvetica, sans-serif; font-size:11px; color:#94a3b8; text-align:center;">
                            © {{ date('Y') }} {{ $brand }}. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
