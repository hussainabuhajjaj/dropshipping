<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    public function makeDataPayload($amount, $chanel = 'card', $currency = "USD")
    {
        $auth = auth('customer')->user();
        return [
            "reference" => 'KPY-' . Str::upper(Str::random(12)) . '-' . time(),
            "amount" => number_format($amount , 2, '.' , ''),
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

        if ($response->failed()) {
            Log::error('Korapay initialize payment failed', []);
            throw new \Exception($response->json()['message'] ?? 'Payment failed');
        }

        return $response->json();
    }



    /**
     * Check transaction status
     */
    public function checkStatus($reference)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
        ])->get($this->baseUrl . '/merchant/api/v1/charges/' . $reference );

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
