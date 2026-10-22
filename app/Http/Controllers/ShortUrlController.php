<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShortUrl;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ShortUrlController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
            'product_id' => 'nullable|exists:products,id',
        ]);

        $shortUrl = ShortUrl::firstOrCreate(
            [
                'original_url' => $request->url,
                'product_id' => $request->product_id,
            ],
            [
                'code' => ShortUrl::generateUniqueCode(),
            ]
        );

        return response()->json([
            'short_url' => url('/s/' . $shortUrl->code),
            'code' => $shortUrl->code,
        ]);
    }

    public function redirect(string $code): RedirectResponse
    {
        // Check if it's a product slug format (/s/p/{slug})
        if (str_starts_with($code, 'p/')) {
            $slug = substr($code, 2);
            return redirect()->route('products.show', ['product' => $slug]);
        }
        
        // Try to decode as base62 product ID
        $productId = $this->decodeBase62($code);
        if ($productId > 0) {
            try {
                $product = \App\Models\Product::where('id', $productId)->where('is_active', true)->first();
                if ($product) {
                    return redirect()->route('products.show', ['product' => $product->slug]);
                }
            } catch (\Exception $e) {
                // Product not found or query failed, continue
            }
        }
        
        // Try to find in database first (skip if table doesn't exist)
        try {
            $shortUrl = ShortUrl::where('code', $code)->first();
            
            if ($shortUrl) {
                $shortUrl->incrementClicks();
                return redirect($shortUrl->original_url);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Table doesn't exist, continue to client-side decoding
        }
        
        // Fallback: try to decode client-side hash (URL-safe base64)
        try {
            // Convert URL-safe base64 back to standard base64
            $standardBase64 = str_replace(['-', '_'], ['+', '/'], $code);
            
            // Calculate required padding
            $padding = strlen($standardBase64) % 4;
            if ($padding > 0) {
                $standardBase64 .= str_repeat('=', 4 - $padding);
            }
            
            $originalUrl = base64_decode($standardBase64);
            if ($originalUrl && filter_var($originalUrl, FILTER_VALIDATE_URL)) {
                return redirect($originalUrl);
            }
        } catch (\Exception $e) {
            // Invalid base64, continue to 404
        }
        
        abort(404);
    }
    
    private function decodeBase62(string $code): int
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $result = 0;
        $length = strlen($code);
        
        for ($i = 0; $i < $length; $i++) {
            $pos = strpos($chars, $code[$i]);
            if ($pos === false) {
                return 0;
            }
            $result = $result * 62 + $pos;
        }
        
        return $result;
    }

    public function forProduct(Product $product): JsonResponse
    {
        $url = route('products.show', $product, false);
        
        $shortUrl = ShortUrl::firstOrCreate(
            [
                'original_url' => url($url),
                'product_id' => $product->id,
            ],
            [
                'code' => ShortUrl::generateUniqueCode(),
            ]
        );

        return response()->json([
            'short_url' => url('/s/' . $shortUrl->code),
            'code' => $shortUrl->code,
        ]);
    }
}
