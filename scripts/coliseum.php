<?php

use App\Services\AiService;
use App\Services\QdrantService;
use Illuminate\Support\Facades\Config;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';

// Bootstrap
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$qdrant = $app->make(QdrantService::class);

// Find a good, meaty entry to test
$testEntry = null;
$results = $qdrant->scroll([], 100, 'default');

foreach ($results as $entry) {
    // Find an entry with substantial content that hasn't been enhanced yet (or just pick a good one)
    if (strlen($entry['content']) > 500) {
        $testEntry = $entry;
        break;
    }
}

if (!$testEntry) {
    // Fallback if no unenhanced entry found
    if ($results->isNotEmpty()) {
        $testEntry = $results->first();
    } else {
        die("No suitable test entry found.\n");
    }
}

echo "--- The Contenders ---\n";
echo "Entry Title: " . $testEntry['title'] . "\n";
echo "Content Length: " . strlen($testEntry['content']) . " chars\n";
echo "Preview: " . substr($testEntry['content'], 0, 100) . "...\n\n";

$models = [
    'x-ai/grok-4-fast' => 'Grok 4 Fast (The Speedster)',
    'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet (The Champion)',
    'google/gemini-pro-1.5' => 'Gemini Pro 1.5 (The Scholar)',
    'meta-llama/llama-3.1-70b-instruct' => 'Llama 3.1 70B (The Workhorse)'
];

foreach ($models as $modelId => $name) {
    echo "Processing with $name ($modelId)...\n";
    
    // Override config
    Config::set('search.ai.provider', 'openrouter'); // Ensure provider is openrouter
    Config::set('search.ai.openrouter.model', $modelId);
    
    // Create fresh instance to pick up new config
    $aiInstance = new AiService();
    
    try {
        $start = microtime(true);
        $result = $aiInstance->enhance([
            'title' => $testEntry['title'],
            'content' => $testEntry['content'],
            'category' => $testEntry['category'] ?? null,
            'tags' => $testEntry['tags'] ?? []
        ]);
        $duration = round(microtime(true) - $start, 2);
        
        if (empty($result['summary']) && empty($result['tags'])) {
            echo "FAILED (Empty Response)\n";
        } else {
            echo "Time: {$duration}s\n";
            echo "Summary: " . ($result['summary'] ?? 'N/A') . "\n";
            echo "Tags: " . implode(', ', $result['tags'] ?? []) . "\n";
            echo "Concepts: " . implode(', ', $result['concepts'] ?? []) . "\n";
            echo "Category: " . ($result['category'] ?? 'N/A') . "\n";
        }
        echo "--------------------------------------------------\n\n";
        
    } catch (\Exception $e) {
        echo "Failed: " . $e->getMessage() . "\n";
    }
}
