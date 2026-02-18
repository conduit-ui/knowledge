<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';

use App\Services\QdrantService;

// Ensure kernel is bootstrapped
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$qdrant = $app->make(QdrantService::class);
$today = date('Y-m-d'); // 2026-02-17 based on prompt context

$projects = ['default', 'jordan'];
$totalNew = 0;

echo "Counting new entries for today ($today)...\n\n";

foreach ($projects as $project) {
    $limit = 100;
    $offset = null;
    $count = 0;
    
    // We can't filter by date directly in Qdrant efficiently without payload index on created_at (which we might have?)
    // But scan is safer.
    
    do {
        $results = $qdrant->scroll([], $limit, $project, $offset);
        
        if ($results->isEmpty()) {
            break;
        }

        foreach ($results as $entry) {
            // Check created_at date
            // Format is usually ISO8601: 2026-02-17T...
            if (isset($entry['created_at']) && str_starts_with($entry['created_at'], $today)) {
                $count++;
            }
            $offset = $entry['id'];
        }
        
    } while ($results->count() >= $limit);
    
    echo "Project '$project': $count new entries\n";
    $totalNew += $count;
}

echo "\nTotal new entries today: $totalNew\n";
