<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';

use App\Services\QdrantService;

// Ensure kernel is bootstrapped
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$qdrant = $app->make(QdrantService::class);

$oldEntryId = 4; // Integer
$projectId = 'default';
$newEntryId = '49a34740-bd2b-4be8-a3c9-f7d1ee27631c';
$supersededDate = '2026-01-24';
$reason = 'Migrated to Qdrant';

echo "Superseding entry #$oldEntryId in project '$projectId'...\n";

// Get old content
$oldEntry = $qdrant->getById($oldEntryId, $projectId);
if (!$oldEntry) {
    die("Entry #$oldEntryId not found in project '$projectId'.\n");
}

$newContent = "**[DEPRECATED]** This concept was superseded by Qdrant on $supersededDate.\n\n" . $oldEntry['content'];

$updateFields = [
    'content' => $newContent,
    'status' => 'deprecated',
    'superseded_by' => $newEntryId,
    'superseded_date' => $supersededDate,
    'superseded_reason' => $reason,
];

// Perform update
$qdrant->updateFields($oldEntryId, $updateFields, $projectId);

echo "Entry #$oldEntryId successfully superseded by #$newEntryId in project '$projectId'.\n";
echo "Status: deprecated\n";
