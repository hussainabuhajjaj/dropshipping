<?php
// Minimal test to see actual error
try {
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    echo "App loaded OK\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n"; 
    echo "Line: " . $e->getLine() . "\n";
}
