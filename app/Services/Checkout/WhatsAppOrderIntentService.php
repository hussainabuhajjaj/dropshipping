<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Domain\Common\Models\Address;
use App\Domain\Orders\Models\OrderAuditLog;
use App\Domain\Products\Models\ProductVariant;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\WhatsAppOrderIntent;
use App\Services\Cart\CartIdentityService;
use App\Services\Currency\CurrencyConversionService;
use App\Services\User\UserPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WhatsAppOrderIntentService
{
    public function __construct(
        private readonly CurrencyConversionService $currencyConverter,
        private readonly CartIdentityService $cartIdentityService,
    ) {
    }

    public function create(Request $request, array $payload): WhatsAppOrderIntent
    {
        $mode = (string) ($payload['mode'] ?? 'product');

        return match ($mode) {
            'cart' => $this->createFromCart($request, $payload),
            default => $this->createFromProduct($request, $payload),
        };
    }

    public function findByReference(string $reference): WhatsAppOrderIntent
    {
        /** @var WhatsAppOrderIntent $intent */
        $intent = WhatsAppOrderIntent::query()
            ->with(['customer', 'convertedOrder', 'convertedByAdmin'])
            ->where('reference', $reference)
            ->firstOrFail();

        if ($intent->status === WhatsAppOrderIntent::STATUS_PENDING && $intent->isExpired()) {
            $intent->markExpired();
            $intent->refresh();
        }

        return $intent;
    }

    public function convert(WhatsAppOrderIntent $intent, array $data, ?int $adminId = null): Order
    {
        if ($intent->status === WhatsAppOrderIntent::STATUS_CONVERTED && $intent->convertedOrder) {
            return $intent->convertedOrder;
        }

        if ($intent->isExpired()) {
            $intent->markExpired();
            throw new RuntimeException('This WhatsApp intent has expired.');
        }

        $validation = $this->validateSnapshot($intent);
        if ($validation['ok'] !== true) {
            $intent->forceFill([
                'last_error_code' => (string) ($validation['code'] ?? 'validation_failed'),
                'last_error_message' => (string) ($validation['message'] ?? 'Snapshot validation failed.'),
            ])->save();

            throw new RuntimeException((string) ($validation['message'] ?? 'Snapshot validation failed.'));
        }

        $customer = $intent->customer;
        $shippingName = trim((string) ($data['name'] ?? $customer?->name ?? 'WhatsApp Customer'));
        $shippingPhone = trim((string) ($data['phone'] ?? $intent->phone ?? $customer?->phone ?? ''));
        $email = trim((string) ($data['email'] ?? $customer?->email ?? Arr::get($intent->snapshot, 'customer.email', '')));
        $line1 = trim((string) ($data['line1'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $country = strtoupper(trim((string) ($data['country'] ?? 'CI')));

        if ($shippingPhone === '' || $email === '' || $line1 === '' || $city === '') {
            throw new RuntimeException('Phone, email, line1, and city are required to convert this intent.');
        }

        /** @var Order $order */
        $order = DB::transaction(function () use ($intent, $customer, $shippingName, $shippingPhone, $email, $line1, $city, $country, $data, $adminId) {
            $address = Address::query()->create([
                'user_id' => null,
                'customer_id' => $customer?->id,
                'name' => $shippingName,
                'phone' => $shippingPhone,
                'line1' => $line1,
                'line2' => filled($data['line2'] ?? null) ? (string) $data['line2'] : null,
                'city' => $city,
                'state' => filled($data['state'] ?? null) ? (string) $data['state'] : null,
                'postal_code' => filled($data['postal_code'] ?? null) ? (string) $data['postal_code'] : null,
                'country' => $country,
                'type' => 'shipping',
            ]);

            $snapshot = (array) $intent->snapshot;
            $totals = (array) ($snapshot['totals'] ?? []);
            $lines = collect($snapshot['lines'] ?? []);

            $order = Order::createWithGeneratedNumber([
                'user_id' => null,
                'customer_id' => $customer?->id,
                'guest_name' => $customer ? null : $shippingName,
                'guest_phone' => $customer ? null : $shippingPhone,
                'is_guest' => $customer === null,
                'email' => $email,
                'locale' => app()->getLocale(),
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'currency' => (string) ($intent->currency ?: 'USD'),
                'subtotal' => (float) ($totals['subtotal'] ?? 0),
                'shipping_total' => (float) ($totals['shipping_total'] ?? 0),
                'shipping_total_estimated' => (float) ($totals['shipping_total'] ?? 0),
                'tax_total' => (float) ($totals['tax_total'] ?? 0),
                'discount_total' => (float) ($totals['discount_total'] ?? 0),
                'grand_total' => (float) ($totals['grand_total'] ?? 0),
                'discount_snapshot' => [
                    'source' => 'whatsapp_intent',
                    'reference' => $intent->reference,
                    'totals' => $totals,
                ],
                'discount_source' => 'whatsapp_intent',
                'shipping_address_id' => $address->id,
                'billing_address_id' => $address->id,
                'shipping_method' => 'whatsapp_assisted',
                'delivery_notes' => (string) ($data['delivery_notes'] ?? ''),
                'placed_at' => now(),
            ]);

            $lines->each(function (array $line) use ($order): void {
                $product = Product::query()->find($line['product_id'] ?? null);
                $variant = ! empty($line['variant_id'])
                    ? ProductVariant::query()->find($line['variant_id'])
                    : null;

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_variant_id' => $variant?->id,
                    'fulfillment_provider_id' => ! empty($line['fulfillment_provider_id'])
                        ? $line['fulfillment_provider_id']
                        : $product?->default_fulfillment_provider_id,
                    'supplier_product_id' => null,
                    'fulfillment_status' => 'pending',
                    'quantity' => (int) ($line['quantity'] ?? 1),
                    'unit_price' => (float) ($line['unit_price'] ?? 0),
                    'total' => (float) ($line['line_total'] ?? 0),
                    'source_sku' => $line['sku'] ?? $variant?->sku,
                    'snapshot' => [
                        'name' => $line['product_name'] ?? $product?->name,
                        'variant' => $line['variant_name'] ?? $variant?->title,
                        'image' => $line['image'] ?? null,
                        'whatsapp_intent_reference' => $order->discount_snapshot['reference'] ?? null,
                    ],
                    'meta' => [
                        'source' => 'whatsapp_intent',
                        'whatsapp_reference' => $order->discount_snapshot['reference'] ?? null,
                    ],
                ]);
            });

            Payment::query()->create([
                'order_id' => $order->id,
                'provider' => 'whatsapp',
                'status' => 'pending',
                'provider_reference' => $intent->reference,
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'meta' => [
                    'type' => 'whatsapp_assisted_checkout',
                    'intent_id' => $intent->id,
                    'intent_reference' => $intent->reference,
                ],
                'paid_at' => null,
            ]);

            OrderAuditLog::query()->create([
                'order_id' => $order->id,
                'user_id' => $adminId,
                'action' => 'whatsapp_intent_converted',
                'note' => "Converted from WhatsApp intent {$intent->reference}.",
                'payload' => [
                    'intent_id' => $intent->id,
                    'reference' => $intent->reference,
                    'channel' => $intent->channel,
                    'intent_type' => $intent->intent_type,
                ],
            ]);

            $intent->forceFill([
                'status' => WhatsAppOrderIntent::STATUS_CONVERTED,
                'converted_at' => now(),
                'converted_order_id' => $order->id,
                'converted_by_admin_id' => $adminId,
                'last_error_code' => null,
                'last_error_message' => null,
            ])->save();

            return $order;
        });

        return $order->fresh(['orderItems', 'payments', 'shippingAddress']);
    }

    public function expireStaleIntents(): int
    {
        return WhatsAppOrderIntent::query()
            ->pending()
            ->expired()
            ->update([
                'status' => WhatsAppOrderIntent::STATUS_EXPIRED,
                'last_error_code' => 'expired',
                'last_error_message' => 'Intent expired before conversion.',
                'updated_at' => now(),
            ]);
    }

    public function buildResponsePayload(WhatsAppOrderIntent $intent): array
    {
        $message = $this->buildWhatsAppMessage($intent);

        return [
            'id' => $intent->id,
            'reference' => $intent->reference,
            'status' => $intent->status,
            'expires_at' => optional($intent->expires_at)->toIso8601String(),
            'message' => $message,
            'whatsapp_url' => $this->buildWhatsAppUrl($message),
            'whatsapp_deeplink' => $this->buildWhatsAppDeepLink($message),
            'totals' => [
                'items_count' => (int) $intent->items_count,
                'subtotal' => (float) $intent->subtotal,
                'shipping_total' => (float) $intent->shipping_total,
                'discount_total' => (float) $intent->discount_total,
                'tax_total' => (float) $intent->tax_total,
                'grand_total' => (float) $intent->grand_total,
                'currency' => $intent->currency,
            ],
        ];
    }

    public function buildWhatsAppMessage(WhatsAppOrderIntent $intent): string
    {
        return trim(sprintf(
            "Hello, I want to place an order.\n\nRef: %s\nItems: %d\nTotal: %s %s",
            $intent->reference,
            (int) $intent->items_count,
            $this->formatAmount((float) $intent->grand_total, (string) $intent->currency),
            $intent->currency
        ));
    }

    public function buildWhatsAppUrl(string $message): string
    {
        $phone = $this->supportWhatsAppNumber();

        return sprintf('https://wa.me/%s?text=%s', $phone, rawurlencode($message));
    }

    public function buildWhatsAppDeepLink(string $message): string
    {
        $phone = $this->supportWhatsAppNumber();

        return sprintf('whatsapp://send?phone=%s&text=%s', $phone, rawurlencode($message));
    }

    public function validateSnapshot(WhatsAppOrderIntent $intent): array
    {
        foreach ((array) ($intent->snapshot['lines'] ?? []) as $line) {
            $product = Product::query()->find($line['product_id'] ?? null);
            if (! $product || ! $product->is_active) {
                return ['ok' => false, 'code' => 'product_unavailable', 'message' => 'One or more products are no longer available.'];
            }

            $variant = ! empty($line['variant_id'])
                ? ProductVariant::query()->find($line['variant_id'])
                : null;

            if (! empty($line['variant_id']) && ! $variant) {
                return ['ok' => false, 'code' => 'variant_missing', 'message' => 'A selected variant is no longer available.'];
            }

            $availableStock = $variant?->stock_on_hand ?? $product->stock_on_hand;
            if ($availableStock !== null && (int) $availableStock < (int) ($line['quantity'] ?? 1)) {
                return ['ok' => false, 'code' => 'stock_unavailable', 'message' => 'Stock is no longer sufficient for this intent.'];
            }

            $liveLine = new CartItem([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'fulfillment_provider_id' => ! empty($line['fulfillment_provider_id'])
                    ? $line['fulfillment_provider_id']
                    : $product->default_fulfillment_provider_id,
                'quantity' => (int) ($line['quantity'] ?? 1),
            ]);
            $liveLine->setRelation('product', $product);
            if ($variant) {
                $liveLine->setRelation('variant', $variant);
            }

            $sourceCurrency = (string) ($line['source_currency'] ?? $line['currency'] ?? $variant?->currency ?? $product->currency ?? 'USD');
            $targetCurrency = (string) ($intent->currency ?: $sourceCurrency);
            $liveUnitPrice = (float) $liveLine->getSinglePrice();
            $liveComparablePrice = array_key_exists('source_unit_price', $line)
                ? $liveUnitPrice
                : $this->convertForIntent($liveUnitPrice, $sourceCurrency, $targetCurrency);
            $snapshotUnitPrice = (float) ($line['source_unit_price'] ?? $line['unit_price'] ?? 0);

            if (round($liveComparablePrice, 2) !== round($snapshotUnitPrice, 2)) {
                return ['ok' => false, 'code' => 'price_changed', 'message' => 'Pricing changed since this WhatsApp intent was created.'];
            }
        }

        return ['ok' => true];
    }

    private function createFromProduct(Request $request, array $payload): WhatsAppOrderIntent
    {
        $product = Product::query()
            ->where('is_active', true)
            ->with(['images', 'variants', 'defaultFulfillmentProvider'])
            ->findOrFail((int) $payload['product_id']);

        $variantId = isset($payload['variant_id']) ? (int) $payload['variant_id'] : null;
        $variant = $variantId
            ? $product->variants->firstWhere('id', $variantId)
            : $product->variants->first();

        $quantity = max(1, (int) ($payload['quantity'] ?? 1));

        $line = new CartItem([
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'fulfillment_provider_id' => $product->default_fulfillment_provider_id,
            'quantity' => $quantity,
            'stock_on_hand' => $variant?->stock_on_hand ?? $product->stock_on_hand,
        ]);
        $line->setRelation('product', $product);
        if ($variant) {
            $line->setRelation('variant', $variant);
        }

        $items = collect([$line]);
        $quote = (new Cart())->quoteShippingForItems($items, false);
        $sourceCurrency = (string) ($variant?->currency ?? $product->currency ?? 'USD');
        $displayCurrency = $this->resolveIntentCurrency($request, $sourceCurrency);
        $sourceSubtotal = (float) ($line->getSinglePrice() * $quantity);
        $shipping = (float) ($quote['total'] ?? 0);
        $settings = SiteSetting::query()->first();
        $sourceShippingTotal = (float) applyShippingRules($shipping, $sourceSubtotal, 0, $settings);
        $sourceTaxTotal = (float) calculateTaxFromSettings($sourceSubtotal, $settings);
        $taxIncluded = (bool) ($settings?->tax_included ?? false);
        $sourceGrandTotal = $sourceSubtotal + $sourceShippingTotal + ($taxIncluded ? 0 : $sourceTaxTotal);
        $subtotal = $this->convertForIntent($sourceSubtotal, $sourceCurrency, $displayCurrency);
        $shippingTotal = $this->convertForIntent($sourceShippingTotal, $sourceCurrency, $displayCurrency);
        $taxTotal = $this->convertForIntent($sourceTaxTotal, $sourceCurrency, $displayCurrency);
        $grandTotal = $this->convertForIntent($sourceGrandTotal, $sourceCurrency, $displayCurrency);

        $snapshot = [
            'customer' => $this->customerSnapshot($request),
            'source' => $this->sourceSnapshot($request, 'product'),
            'totals' => [
                'subtotal' => $subtotal,
                'shipping_total' => $shippingTotal,
                'discount_total' => 0,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'currency' => $displayCurrency,
                'source_currency' => $sourceCurrency,
            ],
            'lines' => [$this->serializeLine($line, $displayCurrency)],
            'shipping_quote' => $quote,
        ];

        return $this->storeIntent($request, [
            'channel' => $this->resolveChannel($payload),
            'intent_type' => 'product',
            'phone' => $payload['phone'] ?? null,
            'currency' => $displayCurrency,
            'items_count' => $quantity,
            'subtotal' => $subtotal,
            'shipping_total' => $shippingTotal,
            'discount_total' => 0,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
            'snapshot' => $snapshot,
        ]);
    }

    private function createFromCart(Request $request, array $payload): WhatsAppOrderIntent
    {
        $cart = $this->resolveCartForIntent($request, $payload);
        $cart->loadMissing(['items.product.images', 'items.variant']);

        if ($cart->items->isEmpty()) {
            throw new RuntimeException('Cart is empty.');
        }

        $summary = $cart->getSummery();
        $sourceCurrency = (string) ($summary['currency'] ?? 'USD');
        $displayCurrency = $this->resolveIntentCurrency($request, $sourceCurrency);
        $subtotal = $this->convertForIntent((float) ($summary['subtotal'] ?? 0), $sourceCurrency, $displayCurrency);
        $shippingTotal = $this->convertForIntent((float) ($summary['shippingTotal'] ?? $summary['shipping'] ?? 0), $sourceCurrency, $displayCurrency);
        $discountTotal = $this->convertForIntent((float) ($summary['discount'] ?? 0), $sourceCurrency, $displayCurrency);
        $taxTotal = $this->convertForIntent((float) ($summary['tax_total'] ?? 0), $sourceCurrency, $displayCurrency);
        $grandTotal = $this->convertForIntent((float) ($summary['total'] ?? 0), $sourceCurrency, $displayCurrency);
        $snapshot = [
            'customer' => $this->customerSnapshot($request),
            'source' => $this->sourceSnapshot($request, 'cart'),
            'totals' => [
                'subtotal' => $subtotal,
                'shipping_total' => $shippingTotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'currency' => $displayCurrency,
                'source_currency' => $sourceCurrency,
            ],
            'lines' => $cart->items->map(fn (CartItem $line) => $this->serializeLine($line, $displayCurrency))->values()->all(),
            'discounts' => [
                'coupon' => $summary['coupon'] ?? null,
                'discount_label' => $summary['discount_label'] ?? null,
                'discount_source' => $summary['discount_source'] ?? null,
                'promotion_discounts' => $summary['promotionDiscounts'] ?? [],
            ],
            'minimum_cart_requirement' => $summary['minimum_cart_requirement'] ?? null,
        ];

        return $this->storeIntent($request, [
            'channel' => $this->resolveChannel($payload),
            'intent_type' => 'cart',
            'phone' => $payload['phone'] ?? null,
            'currency' => $displayCurrency,
            'items_count' => (int) $cart->items->sum('quantity'),
            'subtotal' => $subtotal,
            'shipping_total' => $shippingTotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
            'snapshot' => $snapshot,
        ]);
    }

    private function storeIntent(Request $request, array $attributes): WhatsAppOrderIntent
    {
        $reference = $this->generateReference();
        $snapshot = (array) ($attributes['snapshot'] ?? []);
        $customer = $request->user() instanceof Customer ? $request->user() : auth('customer')->user();
        $guestToken = trim((string) $request->input('guest_token', ''));

        return WhatsAppOrderIntent::query()->create([
            'reference' => $reference,
            'status' => WhatsAppOrderIntent::STATUS_PENDING,
            'channel' => (string) ($attributes['channel'] ?? 'web'),
            'intent_type' => (string) ($attributes['intent_type'] ?? 'product'),
            'customer_id' => $customer?->id,
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'guest_token' => $guestToken !== '' ? $guestToken : null,
            'phone' => $this->sanitizePhone((string) ($attributes['phone'] ?? $customer?->phone ?? '')),
            'currency' => (string) ($attributes['currency'] ?? 'USD'),
            'items_count' => (int) ($attributes['items_count'] ?? 0),
            'subtotal' => (float) ($attributes['subtotal'] ?? 0),
            'shipping_total' => (float) ($attributes['shipping_total'] ?? 0),
            'discount_total' => (float) ($attributes['discount_total'] ?? 0),
            'tax_total' => (float) ($attributes['tax_total'] ?? 0),
            'grand_total' => (float) ($attributes['grand_total'] ?? 0),
            'snapshot' => $snapshot,
            'pricing_hash' => hash('sha256', json_encode([
                'lines' => $snapshot['lines'] ?? [],
                'totals' => $snapshot['totals'] ?? [],
            ], JSON_THROW_ON_ERROR)),
            'snapshot_version' => 1,
            'source_url' => (string) $request->headers->get('origin', $request->fullUrl()),
            'user_agent' => substr((string) $request->userAgent(), 0, 65535),
            'ip_address' => $request->ip(),
            'expires_at' => now()->addHours(2),
        ]);
    }

    private function generateReference(): string
    {
        do {
            $reference = 'WA-' . Str::upper(Str::random(8));
        } while (WhatsAppOrderIntent::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function resolveChannel(array $payload): string
    {
        $channel = strtolower((string) ($payload['channel'] ?? 'web'));

        return in_array($channel, ['web', 'mobile'], true) ? $channel : 'web';
    }

    private function resolveCartForIntent(Request $request, array $payload): Cart
    {
        $customer = $request->user() instanceof Customer ? $request->user() : auth('customer')->user();

        $cart = $this->cartIdentityService->resolveCart($request, $customer, false);

        if (! $cart || ! $cart->items()->exists()) {
            throw new RuntimeException('Cart is empty.');
        }

        return $cart;
    }

    private function serializeLine(CartItem $line, ?string $targetCurrency = null): array
    {
        $product = $line->product;
        $variant = $line->variant;
        $sourceCurrency = (string) ($variant?->currency ?? $product?->currency ?? 'USD');
        $displayCurrency = $targetCurrency ?: $sourceCurrency;
        $sourceUnitPrice = (float) $line->getSinglePrice();
        $sourceLineTotal = (float) ($sourceUnitPrice * (int) $line->quantity);

        return [
            'product_id' => $line->product_id,
            'variant_id' => $line->variant_id,
            'fulfillment_provider_id' => $line->fulfillment_provider_id,
            'quantity' => (int) $line->quantity,
            'sku' => $variant?->sku,
            'unit_price' => $this->convertForIntent($sourceUnitPrice, $sourceCurrency, $displayCurrency),
            'line_total' => $this->convertForIntent($sourceLineTotal, $sourceCurrency, $displayCurrency),
            'currency' => $displayCurrency,
            'source_unit_price' => $sourceUnitPrice,
            'source_line_total' => $sourceLineTotal,
            'source_currency' => $sourceCurrency,
            'product_name' => $product?->name,
            'variant_name' => $variant?->title,
            'image' => $product?->images?->first()?->url,
            'product_slug' => $product?->slug,
        ];
    }

    private function sourceSnapshot(Request $request, string $mode): array
    {
        return [
            'mode' => $mode,
            'path' => $request->path(),
            'url' => $request->fullUrl(),
            'channel' => $request->input('channel', 'web'),
        ];
    }

    private function customerSnapshot(Request $request): array
    {
        $customer = $request->user() instanceof Customer ? $request->user() : auth('customer')->user();

        return [
            'customer_id' => $customer?->id,
            'name' => $customer?->name,
            'email' => $customer?->email,
            'phone' => $customer?->phone,
            'is_guest' => $customer === null,
        ];
    }

    private function supportWhatsAppNumber(): string
    {
        $raw = (string) (SiteSetting::query()->value('support_whatsapp') ?? '22500000000');

        return $this->sanitizePhone($raw) ?: '22500000000';
    }

    private function sanitizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function resolveIntentCurrency(Request $request, string $fallback = 'XOF'): string
    {
        $requested = strtoupper(trim((string) $request->input('currency', '')));
        if ($requested !== '') {
            return $requested;
        }

        try {
            return (string) (app(UserPreferenceService::class)->getPreferences()['currency'] ?? $fallback);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function convertForIntent(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if (strtoupper($fromCurrency) === strtoupper($toCurrency)) {
            return $amount;
        }

        return (float) ($this->currencyConverter->convertAmount($amount, $fromCurrency, $toCurrency) ?? $amount);
    }

    private function formatAmount(float $amount, string $currency): string
    {
        $decimals = strtoupper($currency) === 'XOF' ? 0 : 2;

        return number_format($amount, $decimals, '.', ',');
    }
}
