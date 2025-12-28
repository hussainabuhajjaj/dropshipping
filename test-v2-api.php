<?php

require __DIR__ . '/bootstrap/app.php';

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;

try {
    $client = new CJDropshippingClient();
    
    $response = $client->products()->listProductsV2([
        'pageNum' => 1,
        'pageSize' => 5,
    ]);
    
    if ($response->ok) {
        $data = $response->data;
        echo "Total Products: " . ($data['totalRecords'] ?? 0) . "\n";
        echo "Total Pages: " . ($data['totalPages'] ?? 0) . "\n\n";
        
        $content = $data['content'] ?? [];
        
        foreach ($content as $item) {
            $productList = $item['productList'] ?? [];
            
            foreach ($productList as $product) {
                echo "=== PRODUCT ===\n";
                echo "Name: " . ($product['nameEn'] ?? 'N/A') . "\n";
                echo "CJ PID: " . ($product['id'] ?? 'N/A') . "\n";
                echo "\n--- CATEGORY INFO FROM V2 API ---\n";
                echo "Level 1 (One): " . ($product['oneCategoryName'] ?? 'N/A') . "\n";
                echo "  → CJ ID: " . ($product['oneCategoryId'] ?? 'N/A') . "\n";
                echo "Level 2 (Two): " . ($product['twoCategoryName'] ?? 'N/A') . "\n";
                echo "  → CJ ID: " . ($product['twoCategoryId'] ?? 'N/A') . "\n";
                echo "Level 3 (Three): " . ($product['threeCategoryName'] ?? 'N/A') . "\n";
                echo "  → CJ ID: " . ($product['categoryId'] ?? 'N/A') . "\n";
                echo "\n";
            }
        }
    } else {
        echo "API Error: " . $response->message . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
