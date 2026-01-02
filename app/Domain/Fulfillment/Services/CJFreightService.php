<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Domain\Fulfillment\Clients\CJDropshippingClient;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class CJFreightService
{
    public function __construct(private readonly CJDropshippingClient $client)
    {
    }

    /**
     * Calculate shipping quote for a destination and line items.
     *
     * @param array{country:string,province?:string,city?:string,zip?:string} $destination
     * @param array<int,array{sku:string,quantity:int,weight?:float}> $items
     * @param array $options Additional CJ payload fields (warehouseId, logisticsType, shippingMethod, etc.)
     */
    public function quote(array $destination, array $items, array $options = []): array
    {
        $products = collect($items)
            ->map(function (array $item) {
                return [
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'vid' => $item['vid'] ?? null,
                ];
            })
            ->filter(fn (array $p) => $p['quantity'] > 0 && ! empty($p['vid']))
            ->values()
            ->all();

        if (empty($products)) {
            throw new FulfillmentException('CJ freight quote failed: no variant IDs available for freight calculation.');
        }

        $payload = array_merge([
            'startCountryCode' => strtoupper((string) ($options['from_country'] ?? $options['from_country_code'] ?? $options['ship_from'] ?? 'CN')),
            'endCountryCode' => strtoupper((string) $destination['country']),
            'zip' => $destination['zip'] ?? null,
            'products' => $products,
        ], Arr::only($options, ['taxId', 'houseNumber', 'iossNumber']));

        try {
            $data = $this->callFreight($payload);
            if (empty($data)) {
                $data = $this->callFreight($payload, true); // fallback to tip endpoint
            }

            // Normalize into list of options: logisticName, price, estimatedDays
            $normalized = collect(is_array($data) ? $data : [])
                ->map(function ($entry) {
                    $logistic = $entry['logisticName'] ?? $entry['logisticsName'] ?? $entry['shippingMethodName'] ?? null;
                    $price = isset($entry['freight']) ? (float) $entry['freight'] : (isset($entry['price']) ? (float) $entry['price'] : null);
                    $currency = $entry['currency'] ?? $entry['currencyCode'] ?? null;
                    $minDays = $entry['dayMin'] ?? $entry['minDay'] ?? null;
                    $maxDays = $entry['dayMax'] ?? $entry['maxDay'] ?? null;
                    $eta = null;
                    if (is_numeric($minDays) || is_numeric($maxDays)) {
                        $eta = [
                            'min' => $minDays !== null ? (int) $minDays : null,
                            'max' => $maxDays !== null ? (int) $maxDays : null,
                        ];
                    } elseif (! empty($entry['deliveryTime'])) {
                        $eta = ['label' => (string) $entry['deliveryTime']];
                    }

                    return array_filter([
                        'logisticName' => $logistic,
                        'price' => $price,
                        'currency' => $currency,
                        'estimatedDays' => $eta,
                        'raw' => $entry,
                    ], fn ($v) => $v !== null);
                })
                ->filter(fn ($opt) => array_key_exists('logisticName', $opt) && array_key_exists('price', $opt))
                ->values()
                ->all();

            return $normalized;
        } catch (\Throwable $e) {
            Log::warning('CJ freight quote failed', [
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);
            throw new FulfillmentException('CJ freight quote failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function callFreight(array $payload, bool $useTip = false): array
    {
        $response = $useTip
            ? $this->client->freightCalculateTip(['reqDTOS' => [$payload]])
            : $this->client->freightCalculate($payload);

        $data = $response->data ?? [];
        if (! is_array($data)) {
            throw new FulfillmentException('CJ freight quote error: invalid response data type.');
        }

        return $data;
    }
}
