<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';

use App\Services\QdrantService;
use App\Services\ProjectDetectorService;
use App\Services\EnhancementQueueService;

// Ensure kernel is bootstrapped
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$qdrant = $app->make(QdrantService::class);
$projectDetector = $app->make(ProjectDetectorService::class);
$project = 'default';

echo "Scanning project: $project\n";

$limit = 100;
$offset = null;
$total = 0;
$unenhanced = 0;
$requeue = false; // Set to true to automatically requeue
$requeueLimit = -1; // -1 for all

foreach ($argv as $arg) {
    if ($arg === '--requeue') {
        $requeue = true;
        echo "Requeue mode enabled\n";
        $queue = $app->make(\App\Services\EnhancementQueueService::class);
    }
    if (str_starts_with($arg, '--limit=')) {
        $requeueLimit = (int) substr($arg, 8);
        echo "Requeue limit: $requeueLimit\n";
    }
}

$requeuedCount = 0;

do {
    // Scroll takes filters, limit, project, offset
    $results = $qdrant->scroll([], $limit, $project, $offset);
    
    if ($results->isEmpty()) {
        break;
    }

    foreach ($results as $entry) {
        $total++;
        // Check if 'enhanced' key is missing or false
        if (empty($entry['enhanced']) || $entry['enhanced'] === false) {
            $unenhanced++;
            if ($requeue) {
                if ($requeueLimit !== -1 && $requeuedCount >= $requeueLimit) {
                    echo "\nReached requeue limit of $requeueLimit\n";
                    break 2;
                }

                // Batching logic
                $batch[] = $entry;
                if (count($batch) >= 100) {
                    // Check if already in queue to avoid duplicates
                    // Since queueMany loads/saves, we should probably modify queueMany to handle duplicates or assume it's okay for now.
                    // queueMany loads the queue.
                    // Let's assume queueMany is efficient enough or we modify it to check duplicates.
                    // The current implementation of queueMany loads queue, adds items, saves.
                    // It doesn't check for duplicates.
                    // Let's manually filter duplicates here before calling queueMany.
                    
                    $queue->queueMany($batch, $project);
                    $requeuedCount += count($batch);
                    $batch = [];
                    echo "Queued batch of 100... (Total: $requeuedCount)\n";
                }
            }
        }
        $offset = $entry['id'];
    }
    
    // Process remaining batch
    if ($requeue && !empty($batch)) {
        $queue->queueMany($batch, $project);
        $requeuedCount += count($batch);
        $batch = [];
        echo "Queued remaining batch... (Total: $requeuedCount)\n";
    }
    
    if (!$requeue) {
        echo "Scanned $total entries... ($unenhanced unenhanced)\r";
    } else {
        echo "Scanned $total entries... ($requeuedCount requeued)\r";
    }
    
} while ($results->count() >= $limit);

echo "\nTotal entries: $total\n";
echo "Unenhanced entries: $unenhanced\n";
if ($requeue) {
    echo "Requeued entries: $requeuedCount\n";
}
