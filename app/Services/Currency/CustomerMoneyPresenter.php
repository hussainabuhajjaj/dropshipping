<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Customer-facing money presentation:
 * - Always display XOF
 * - Convert from order/payment currency using (1) payment.meta.fx_rate_used for USD->XOF when available
 *   otherwise configured FX rates (config('currency.rates')).
 */
class CustomerMoneyPresenter
{
    public const DISPLAY_CURRENCY = 'XOF';

    public function __construct(private readonly CurrencyConversionService $converter)
    {
    }

    public function displayCurrency(): string
    {
        return self::DISPLAY_CURRENCY;
    }

    public function decimals(string $currency): int
    {
        $currency = strtoupper(trim($currency));
        $configured = config('currency.decimals.' . $currency);
        if (is_numeric($configured)) {
            return (int) $configured;
        }

        // Sensible fallback: XOF has no minor unit.
        return in_array($currency, ['XOF', 'XAF'], true) ? 0 : 2;
    }

    public function format(float $amount, ?string $currency = null): string
    {
        $currency = strtoupper(trim($currency ?: self::DISPLAY_CURRENCY));
        $decimals = $this->decimals($currency);

        return number_format($amount, $decimals, '.', ',');
    }

    /**
     * @return array{amount: float, currency: string, formatted: string, ok: bool, note: string|null}
     */
    public function present(float $amount, string $fromCurrency, ?Payment $payment = null): array
    {
        [$converted, $ok, $note] = $this->convertToDisplayCurrency($amount, $fromCurrency, $payment);

        return [
            'amount' => $converted,
            'currency' => self::DISPLAY_CURRENCY,
            'formatted' => $this->format($converted, self::DISPLAY_CURRENCY),
            'ok' => $ok,
            'note' => $note,
        ];
    }

    /**
     * Convert any currency amount to XOF for customer display.
     *
     * @return array{0: float, 1: bool, 2: string|null} [amountXof, ok, note]
     */
    public function convertToDisplayCurrency(float $amount, string $fromCurrency, ?Payment $payment = null): array
    {
        $from = $this->converter->normalize((string) $fromCurrency);
        $to = self::DISPLAY_CURRENCY;

        if ($from === $to) {
            return [round($amount, $this->decimals($to)), true, null];
        }

        // Preferred: use the exact payment FX used at charge-time when available (USD -> XOF).
        $fxRate = null;
        if ($from === 'USD' && $payment && is_array($payment->meta)) {
            $metaRate = $payment->meta['fx_rate_used'] ?? null;
            if (is_numeric($metaRate) && (float) $metaRate > 0) {
                $fxRate = (float) $metaRate;
            }
        }

        try {
            $rate = $fxRate ?: $this->converter->rate($from, $to);
            $converted = $amount * $rate;

            return [round($converted, $this->decimals($to)), true, null];
        } catch (\Throwable $e) {
            Log::error('CustomerMoneyPresenter: FX unavailable for conversion', [
                'from' => $from,
                'to' => $to,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            // Keep the email sending, but mark conversion as unreliable.
            return [round($amount, $this->decimals($to)), false, 'FX unavailable for conversion'];
        }
    }

    /**
     * @return array{
     *   currency: string,
     *   ok: bool,
     *   note: string|null,
     *   subtotal: float,
     *   shipping: float,
     *   tax: float,
     *   discount: float,
     *   total: float,
     * }
     */
    public function presentOrderTotals(Order $order, ?Payment $payment = null): array
    {
        $fromCurrency = (string) ($order->currency ?? config('currency.base', 'USD'));

        $subtotal = $this->present((float) ($order->subtotal ?? 0), $fromCurrency, $payment);
        $shipping = $this->present((float) ($order->shipping_total ?? 0), $fromCurrency, $payment);
        $tax = $this->present((float) ($order->tax_total ?? 0), $fromCurrency, $payment);
        $discount = $this->present((float) ($order->discount_total ?? 0), $fromCurrency, $payment);
        $total = $this->present((float) ($order->grand_total ?? 0), $fromCurrency, $payment);

        $ok = (bool) ($subtotal['ok'] && $shipping['ok'] && $tax['ok'] && $discount['ok'] && $total['ok']);
        $note = $ok ? null : 'FX unavailable for conversion';

        return [
            'currency' => self::DISPLAY_CURRENCY,
            'ok' => $ok,
            'note' => $note,
            'subtotal' => (float) $subtotal['amount'],
            'shipping' => (float) $shipping['amount'],
            'tax' => (float) $tax['amount'],
            'discount' => (float) $discount['amount'],
            'total' => (float) $total['amount'],
        ];
    }
}

