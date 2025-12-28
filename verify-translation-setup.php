#!/usr/bin/env php
<?php

// Quick verification script for translation tracking setup

echo "\n=== Translation Tracking Setup Verification ===\n\n";

// 1. Check database columns
echo "1. Checking database columns...\n";
try {
    $pdo = new PDO(
        'mysql:host=' . env('DB_HOST') . ';dbname=' . env('DB_DATABASE'),
        env('DB_USERNAME'),
        env('DB_PASSWORD')
    );
    
    $result = $pdo->query("SHOW COLUMNS FROM products LIKE 'translation_%'");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($columns) >= 3) {
        echo "   ✅ Found translation columns: " . implode(', ', array_column($columns, 'Field')) . "\n";
    } else {
        echo "   ❌ Missing translation columns. Run: php artisan migrate\n";
    }
} catch (Exception $e) {
    echo "   ⚠️  Could not connect to database: " . $e->getMessage() . "\n";
}

// 2. Check model setup
echo "\n2. Checking Product model...\n";
$productPath = base_path('app/Domain/Products/Models/Product.php');
if (file_exists($productPath)) {
    $content = file_get_contents($productPath);
    if (strpos($content, 'translation_status') !== false) {
        echo "   ✅ Product model has translation fields\n";
    } else {
        echo "   ❌ Product model missing translation fields\n";
    }
} else {
    echo "   ❌ Product model not found at: $productPath\n";
}

// 3. Check Livewire component
echo "\n3. Checking Livewire component...\n";
$componentPath = base_path('app/Livewire/TranslationProgress.php');
if (file_exists($componentPath)) {
    echo "   ✅ TranslationProgress.php exists\n";
} else {
    echo "   ❌ TranslationProgress.php not found\n";
}

// 4. Check Blade view
echo "\n4. Checking Blade view...\n";
$viewPath = base_path('resources/views/livewire/translation-progress.blade.php');
if (file_exists($viewPath)) {
    echo "   ✅ translation-progress.blade.php exists\n";
} else {
    echo "   ❌ translation-progress.blade.php not found\n";
}

// 5. Check DeepSeek configuration
echo "\n5. Checking translation provider...\n";
$provider = env('TRANSLATION_PROVIDER', 'libre_translate');
$deepseekKey = env('DEEPSEEK_API_KEY');

if ($provider === 'deepseek') {
    if ($deepseekKey) {
        echo "   ✅ DeepSeek provider configured (key starts with: " . substr($deepseekKey, 0, 10) . "...)\n";
    } else {
        echo "   ⚠️  DeepSeek provider selected but no API key. Add DEEPSEEK_API_KEY to .env\n";
    }
} else {
    echo "   ⓘ  Using provider: $provider\n";
}

// 6. Check queue is configured
echo "\n6. Checking queue configuration...\n";
$queueDriver = env('QUEUE_CONNECTION', 'sync');
if ($queueDriver !== 'sync') {
    echo "   ✅ Queue configured with driver: $queueDriver\n";
    echo "   💡 Start queue worker with: php artisan queue:work --queue=default\n";
} else {
    echo "   ⚠️  Queue using sync driver (synchronous). Jobs will run immediately.\n";
}

// 7. Check job configuration
echo "\n7. Checking TranslateProductJob...\n";
$jobPath = base_path('app/Jobs/TranslateProductJob.php');
if (file_exists($jobPath)) {
    $jobContent = file_get_contents($jobPath);
    if (strpos($jobContent, 'translation_status') !== false) {
        echo "   ✅ TranslateProductJob configured with status tracking\n";
    } else {
        echo "   ❌ TranslateProductJob missing status tracking\n";
    }
} else {
    echo "   ❌ TranslateProductJob not found\n";
}

echo "\n=== Verification Complete ===\n\n";
echo "Next steps:\n";
echo "1. Open Filament Admin at: http://localhost/admin\n";
echo "2. Go to Products resource\n";
echo "3. Click the floating button (⏰) in bottom-right corner\n";
echo "4. Click 'Translate' on a product\n";
echo "5. Watch the modal update in real-time as the job processes\n\n";

// Helper function
function env($key, $default = null)
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    return $value ?? $default;
}
