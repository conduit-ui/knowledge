<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';

use App\Services\QdrantService;

// Ensure kernel is bootstrapped
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$qdrant = $app->make(QdrantService::class);
$today = date('Y-m-d'); // 2026-02-17 based on prompt context
$keyword = 'pstrax';

$projects = ['default', 'jordan'];
$foundEntries = [];

echo "Searching for '$keyword' in entries created today ($today)...\n\n";

foreach ($projects as $project) {
    $limit = 100;
    $offset = null;
    
    do {
        $results = $qdrant->scroll([], $limit, $project, $offset);
        
        if ($results->isEmpty()) {
            break;
        }

        foreach ($results as $entry) {
            // Check date
            if (isset($entry['created_at']) && str_starts_with($entry['created_at'], $today)) {
                // Check keyword (disabled for listing all)
                // if (stripos($entry['title'], $keyword) !== false || stripos($entry['content'], $keyword) !== false) {
                    $foundEntries[] = $entry;
                // }
            }
            $offset = $entry['id'];
        }
        
    } while ($results->count() >= $limit);
}

if (empty($foundEntries)) {
    echo "No entries found matching '$keyword' from today.\n";
} else {
    foreach ($foundEntries as $entry) {
        echo "--- Found Entry ---\n";
        echo "ID: " . $entry['id'] . "\n";
        echo "Title: " . $entry['title'] . "\n";
        echo "Created: " . $entry['created_at'] . "\n";
        echo "Content: " . $entry['content'] . "\n\n";
    }
}
