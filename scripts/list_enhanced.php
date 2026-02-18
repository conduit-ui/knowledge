<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';

use App\Services\QdrantService;

// Ensure kernel is bootstrapped
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$qdrant = $app->make(QdrantService::class);
$project = 'default';

$limit = 100;
$offset = null;
$found = 0;
$maxDisplay = 5;

echo "Scanning for enhanced entries...\n\n";

do {
    $results = $qdrant->scroll([], $limit, $project, $offset);
    
    if ($results->isEmpty()) {
        break;
    }

    foreach ($results as $entry) {
        if (!empty($entry['enhanced']) && $entry['enhanced'] === true) {
            $found++;
            if ($found <= $maxDisplay) {
                echo "--- Entry #$found ---\n";
                echo "Title: " . $entry['title'] . "\n";
                echo "Category: " . ($entry['category'] ?? 'N/A') . "\n";
                echo "Tags: " . implode(', ', $entry['tags'] ?? []) . "\n";
                echo "Concepts: " . implode(', ', $entry['concepts'] ?? []) . "\n";
                echo "AI Summary: " . ($entry['summary'] ?? 'N/A') . "\n";
                echo "\n";
            }
        }
        $offset = $entry['id'];
    }
    
} while ($results->count() >= $limit && $found < $maxDisplay);

if ($found === 0) {
    echo "No enhanced entries found.\n";
}
