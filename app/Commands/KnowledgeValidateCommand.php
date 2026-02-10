<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeValidateCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'validate {id : The ID of the entry to validate}';

    /**
     * @var string
     */
    protected $description = 'Mark an entry as validated and boost its confidence';

    public function handle(QdrantService $qdrant): int
    {
        $id = $this->argument('id');

        $entry = $qdrant->getById($id);

        if ($entry === null) {
            $this->error("Entry not found with ID: {$id}");

            return self::FAILURE;
        }

        $oldConfidence = $entry['confidence'];
        $oldStatus = $entry['status'];

        // Calculate new confidence (boosted by validation)
        $newConfidence = min(100, $oldConfidence + 20);

        // Update entry with validated status and verification timestamp
        $qdrant->updateFields($id, [
            'status' => 'validated',
            'confidence' => $newConfidence,
            'last_verified' => now()->toIso8601String(),
        ]);

        $this->info("Entry #{$id} validated successfully!");
        $this->newLine();

        $this->line("Title: {$entry['title']}");
        $this->line("Status: {$oldStatus} -> validated");
        $this->line("Confidence: {$oldConfidence}% -> {$newConfidence}%");

        $this->newLine();
        $this->comment('The entry has been marked as validated and its confidence has been updated.');

        return self::SUCCESS;
    }
}
