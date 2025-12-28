<?php
// Direct SQL to see what's wrong

$db = new PDO('mysql:host=localhost;dbname=cj_dropshipping', 'root', '');

echo "=== CATEGORIES WITH IMPROPER NAMES (containing >) ===\n\n";

$stmt = $db->prepare("SELECT * FROM categories WHERE name LIKE '%>%' ORDER BY name");
$stmt->execute();
$cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($cats) . " categories with > in their name (should be split):\n\n";

foreach ($cats as $cat) {
    echo "ID: {$cat['id']}, Name: {$cat['name']}, Parent: {$cat['parent_id']}, CJ ID: {$cat['cj_id']}\n";
}

if (count($cats) === 0) {
    echo "✓ No problematic categories found!\n";
}

echo "\n=== DUPLICATE CATEGORIES BY NAME ===\n\n";

$stmt = $db->prepare("
    SELECT name, parent_id, COUNT(*) as cnt 
    FROM categories 
    GROUP BY name, parent_id 
    HAVING cnt > 1
");
$stmt->execute();
$dupes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($dupes) . " duplicate category names:\n\n";
foreach ($dupes as $dupe) {
    echo "Name: {$dupe['name']}, Parent: {$dupe['parent_id']}, Count: {$dupe['cnt']}\n";
}

if (count($dupes) === 0) {
    echo "✓ No duplicates found!\n";
}

echo "\n";
