<?php

// Simple test to check if Filament admin can load
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

try {
    // Test if we can boot the application
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    
    // Create a test request to the admin panel
    $request = Illuminate\Http\Request::create('/admin', 'GET');
    
    // Disable error reporting to see what's actually being output
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
    
    echo "Testing admin panel load...\n";
    
    // Try to handle the request
    $response = $kernel->handle($request);
    
    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Content Length: " . strlen($response->getContent()) . "\n";
    
    // Check for PHP errors in the output
    $content = $response->getContent();
    if (strpos($content, 'Fatal error') !== false || 
        strpos($content, 'Parse error') !== false ||
        strpos($content, 'Warning:') !== false) {
        echo "\n=== PHP ERRORS DETECTED ===\n";
        echo substr($content, 0, 2000);
    } else {
        echo "\nAdmin panel loaded successfully!\n";
    }
    
} catch (Throwable $e) {
    echo "\n=== ERROR ===\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}
