<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';

use App\Services\QdrantService;

// Ensure kernel is bootstrapped
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$qdrant = $app->make(QdrantService::class);
$projectId = 'default';
$id = 1;

echo "Restoring entry #$id...\n";

$entry = $qdrant->getById($id, $projectId);
if (!$entry) {
    die("Entry #$id not found.\n");
}

$content = $entry['content'];
// Remove the deprecation notice
$content = str_replace("**[DEPRECATED]** This concept was superseded by Qdrant on 2026-01-24.\n\n", "", $content);

$updateFields = [
    'content' => $content,
    'status' => 'validated',
    'superseded_by' => null,
    'superseded_date' => null,
    'superseded_reason' => null,
];

$qdrant->updateFields($id, $updateFields, $projectId);

echo "Entry #$id restored.\n";
