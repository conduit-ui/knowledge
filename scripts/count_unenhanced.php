<?php

use App\Services\QdrantService;
use App\Services\ProjectDetectorService;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';

// Bootstrap the console kernel to ensure services are registered
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$qdrant = $app->make(QdrantService::class);
$projectDetector = $app->make(ProjectDetectorService::class);
// Force default project for now to match what we saw in the logs (knowledge_default)
// But let's check what detect returns
$project = 'default'; 

echo "Scanning project: $project\n";

$limit = 100;
$offset = null;
$total = 0;
$unenhanced = 0;

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
        }
        $offset = $entry['id'];
    }
    
    echo "Scanned $total entries... ($unenhanced unenhanced)\r";
    
} while ($results->count() >= $limit);

echo "\nTotal entries: $total\n";
echo "Unenhanced entries: $unenhanced\n";
