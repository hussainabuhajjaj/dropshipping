<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Cache;
use NumberFormatter;

class AdminCurrencyService
{
    /**
     * Format any amount as USD for admin panel display
     */
    public static function formatAsUsd(float $amount, ?string $fromCurrency = null): string
    {
        // If amount is already in USD or no source currency, format directly
        if (!$fromCurrency || $fromCurrency === 'USD') {
            return '$' . number_format($amount, 2);
        }
        
        // Convert to USD first if needed
        $usdAmount = self::convertToUsd($amount, $fromCurrency);
        return '$' . number_format($usdAmount, 2);
    }
    
    /**
     * Convert amount to USD if needed
     */
    private static function convertToUsd(float $amount, string $fromCurrency): float
    {
        if ($fromCurrency === 'USD') {
            return $amount;
        }
        
        // Use cached exchange rates
        $cacheKey = "exchange_rate_{$fromCurrency}_USD";
        $rate = Cache::remember($cacheKey, 3600, function () use ($fromCurrency) {
            $rates = config('currency.rates', []);
            $directKey = "{$fromCurrency}_USD";
            $inverseKey = "USD_{$fromCurrency}";
            
            if (isset($rates[$directKey]) && is_numeric($rates[$directKey])) {
                return (float) $rates[$directKey];
            }
            
            if (isset($rates[$inverseKey]) && is_numeric($rates[$inverseKey])) {
                return 1 / (float) $rates[$inverseKey];
            }
            
            // If no rate found, return original amount (don't convert)
            return 1.0;
        });
        
        return $amount * $rate;
    }
    
    /**
     * Get USD symbol for display
     */
    public static function getUsdSymbol(): string
    {
        return '$';
    }
    
    /**
     * Format price for admin panel (always USD)
     */
    public static function formatPrice(float $price, ?string $currency = null): string
    {
        return self::formatAsUsd($price, $currency);
    }
    
    /**
     * Format cost price for admin panel (always USD)
     */
    public static function formatCost(float $cost, ?string $currency = null): string
    {
        return self::formatAsUsd($cost, $currency);
    }
    
    /**
     * Format margin percentage for admin panel
     */
    public static function formatMargin(float $margin): string
    {
        return number_format($margin, 2) . '%';
    }
}
