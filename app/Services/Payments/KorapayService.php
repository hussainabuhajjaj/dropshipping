<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KorapayService
{
    protected $secretKey;
    protected $publicKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('korapay.secret_key');
        $this->publicKey = config('korapay.public_key');
        $this->baseUrl = config('korapay.baseUrl');
    }

    public function chargeCard(array $data): array
    {
        try {
            // Ensure amount is formatted correctly for the currency
            $amount = $this->formatAmountForCurrency($data['amount'], $data['currency']);

            $chargeData = [
                'charge_data' => $this->encryptAES256([
                    'amount' => $amount,
                    'currency' => $data['currency'],
                    'reference' => $data['reference'],
                    'card' => $data['card'],
                    'customer' => $data['customer']
                ])
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/charges/card', $chargeData);

            if ($response->failed()) {
                Log::error('Korapay charge failed', [
                    'currency' => $data['currency'],
                    'amount' => $amount,
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);

                return [
                    'success' => false,
                    'message' => $response->json()['message'] ?? 'Payment failed'
                ];
            }

            $responseData = $response->json();
            dd($responseData);
            return [
                'success' => true,
                'data' => $responseData['data'] ?? $responseData
            ];

        } catch (\Exception $e) {
            Log::error('Korapay charge exception: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Payment processing error'
            ];
        }
    }

    public function makeDataPayload($amount, $chanel = 'card', $currency = "USD")
    {
        $auth = auth('customer')->user();
        return [
            "reference" => (string)now()->timestamp, // must be at least 8 characters
            "amount" => $amount,
            "currency" => $currency,
            "redirect_url" => route('pay.redirect', ['type' => request()->route('type'), 'id' => request()->route('id')]),
            "customer" => [
                "name" => $auth->name,
                "email" => $auth->email,
            ],
            'channels' => [$chanel],
            'default_channel' => $chanel,
        ];
    }

    /**
     * Initialize a payment
     */
    public function initializePayment($amount, $method = "card")
    {
        // for card payload
        if ($method == "mobile_money"){
            $currency = "XOF";
        }else{
            $currency = "USD";
        }
        $payload = $this->makeDataPayload($amount, $method , $currency);


        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/merchant/api/v1/charges/initialize', $payload);

//        dd($response->json());
        if ($response->failed()) {
            Log::error('Korapay initialize payment failed', []);
            throw new \Exception($response->json()['message'] ?? 'Payment failed');
        }

        return $response->json();
    }


    /**
     * Initiate mobile money payment
     */
    public function initiateMobileMoney(array $data)
    {
        $payload = [
            'reference' => $data['reference'],
            'amount' => $data['amount'] * 100,
            'currency' => $data['currency'],
            'mobile_money' => [
                'phone' => $data['mobile_number'],
                'provider' => $data['provider'],
            ],
            'customer' => [
                'email' => $data['email'],
                'name' => $data['name'] ?? null,
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/merchant/api/v1/charges/mobile-money', $payload);

        return $response->json();
    }

    /**
     * Check transaction status
     */
    public function checkStatus($reference)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
        ])->get($this->baseUrl . '/merchant/api/v1/charges/' . $reference . '/status');

        return $response->json();
    }


    private function formatAmountForCurrency(float $amount, string $currency): float
    {
        // Korapay might expect different formats per currency
        return match ($currency) {
            'USD', 'EUR', 'GBP' => round($amount, 2), // 2 decimal places for fiat
            'NGN' => round($amount, 2), // NGN also uses 2 decimals
            default => round($amount, 2),
        };
    }


}
