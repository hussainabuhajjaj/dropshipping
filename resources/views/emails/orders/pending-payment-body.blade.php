@php
    $supportEmail = config('mail.from.address', 'support@example.com');
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
    <tr>
        <td style="font-size:16px; font-weight:700; color:#0f172a;">
            Hi {{ e($name) }},
        </td>
    </tr>
    <tr>
        <td style="padding-top:6px; color:#334155;">
            Your order is reserved and ready. Complete payment to lock in your items and start processing.
        </td>
    </tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:12px; padding:12px; margin-bottom:18px;">
    <tr>
        <td style="font-size:13px; color:#64748b;">Order</td>
        <td align="right" style="font-size:13px; font-weight:700; color:#0f172a;">#{{ e($order->number) }}</td>
    </tr>
    <tr>
        <td style="font-size:13px; color:#64748b; padding-top:6px;">Amount due</td>
        <td align="right" style="font-size:16px; font-weight:800; color:#0f172a; padding-top:6px;">
            {{ $currency }} {{ $summary['grand_total'] }}
        </td>
    </tr>
    <tr>
        <td colspan="2" style="padding-top:8px; font-size:12px; color:#64748b;">
            Need to update payment method? You can complete payment in one click.
            <a href="{{ $paymentUrl }}" style="color:#0ea5e9; text-decoration:none; font-weight:700;">Pay now</a>
        </td>
    </tr>
</table>

<div style="font-size:13px; font-weight:700; color:#0f172a; margin-bottom:8px;">Items in your order</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    @foreach($items as $item)
        <tr>
            <td width="84" valign="top" style="padding:10px 0;">
                @if(!empty($item['image']))
                    <img src="{{ $item['image'] }}" alt="{{ e($item['name']) }}" width="72" height="72" style="border-radius:10px; object-fit:cover; border:1px solid #e5e7eb;">
                @else
                    <div style="width:72px; height:72px; border-radius:10px; background:#f1f5f9; border:1px solid #e5e7eb;"></div>
                @endif
            </td>
            <td valign="top" style="padding:10px 0 10px 12px;">
                <div style="font-size:14px; font-weight:700; color:#0f172a;">
                    {{ e($item['name']) }}
                </div>
                @if(!empty($item['variant']))
                    <div style="font-size:12px; color:#64748b; padding-top:2px;">
                        Variant: {{ e($item['variant']) }}
                    </div>
                @endif
                <div style="font-size:12px; color:#64748b; padding-top:4px;">
                    Qty {{ $item['qty'] }} · {{ $currency }} {{ $item['unit'] }}
                </div>
            </td>
            <td align="right" valign="top" style="padding:10px 0; font-size:14px; font-weight:700; color:#0f172a;">
                {{ $currency }} {{ $item['total'] }}
            </td>
        </tr>
        <tr>
            <td colspan="3" style="border-bottom:1px solid #e5e7eb;"></td>
        </tr>
    @endforeach
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;">
    <tr>
        <td style="font-size:13px; color:#64748b;">Subtotal</td>
        <td align="right" style="font-size:13px; color:#0f172a;">{{ $currency }} {{ $summary['subtotal'] }}</td>
    </tr>
    <tr>
        <td style="font-size:13px; color:#64748b; padding-top:6px;">Shipping</td>
        <td align="right" style="font-size:13px; color:#0f172a; padding-top:6px;">{{ $currency }} {{ $summary['shipping'] }}</td>
    </tr>
    <tr>
        <td style="font-size:13px; color:#64748b; padding-top:6px;">Tax</td>
        <td align="right" style="font-size:13px; color:#0f172a; padding-top:6px;">{{ $currency }} {{ $summary['tax'] }}</td>
    </tr>
    @if((float) $summary['discount'] > 0)
        <tr>
            <td style="font-size:13px; color:#64748b; padding-top:6px;">Discount</td>
            <td align="right" style="font-size:13px; color:#0f172a; padding-top:6px;">-{{ $currency }} {{ $summary['discount'] }}</td>
        </tr>
    @endif
    <tr>
        <td style="font-size:14px; font-weight:800; color:#0f172a; padding-top:10px;">Total due now</td>
        <td align="right" style="font-size:14px; font-weight:800; color:#0f172a; padding-top:10px;">
            {{ $currency }} {{ $summary['grand_total'] }}
        </td>
    </tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px; background:#f8fafc; border-radius:12px; border:1px solid #e5e7eb;">
    <tr>
        <td style="padding:14px 16px; font-size:12px; color:#64748b;">
            Need help completing your payment? Reply to this email or reach us at {{ $supportEmail }}.
        </td>
    </tr>
</table>
