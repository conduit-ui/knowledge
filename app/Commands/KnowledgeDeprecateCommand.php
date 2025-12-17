<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use App\Models\Relationship;
use App\Services\RelationshipService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeDeprecateCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'deprecate
                            {id : The ID of the entry to deprecate}
                            {--replacement= : The ID of the replacement entry}';

    /**
     * @var string
     */
    protected $description = 'Mark an entry as deprecated with optional replacement';

    public function handle(RelationshipService $relationshipService): int
    {
        $id = $this->argument('id');
        $replacementId = $this->option('replacement');

        if (! is_numeric($id)) {
            $this->error('Entry ID must be a number.');

            return self::FAILURE;
        }

        /** @var Entry|null $entry */
        $entry = Entry::query()->find((int) $id);

        if ($entry === null) {
            $this->error("Entry not found with ID: {$id}");

            return self::FAILURE;
        }

        if ($entry->status === 'deprecated') {
            $this->warn("Entry #{$id} is already deprecated.");

            return self::SUCCESS;
        }

        // Validate replacement entry if provided
        $replacementEntry = null;
        if ($replacementId !== null) {
            if (! is_numeric($replacementId)) {
                $this->error('Replacement ID must be a number.');

                return self::FAILURE;
            }

            /** @var Entry|null $replacementEntry */
            $replacementEntry = Entry::query()->find((int) $replacementId);

            if ($replacementEntry === null) {
                $this->error("Replacement entry not found with ID: {$replacementId}");

                return self::FAILURE;
            }

            if ((int) $replacementId === (int) $id) {
                $this->error('An entry cannot replace itself.');

                return self::FAILURE;
            }
        }

        // Update entry status
        $oldStatus = $entry->status;
        $entry->update([
            'status' => 'deprecated',
            'confidence' => 0,
        ]);

        $this->info("Entry #{$id} has been deprecated.");
        $this->newLine();
        $this->line("Title: {$entry->title}");
        $this->line("Status: {$oldStatus} -> deprecated");
        $this->line('Confidence: 0%');

        // Create replacement relationship if provided
        if ($replacementEntry !== null) {
            $relationshipService->createRelationship(
                (int) $id,
                (int) $replacementId,
                Relationship::TYPE_REPLACED_BY
            );

            $this->newLine();
            $this->info("Linked to replacement: #{$replacementEntry->id} {$replacementEntry->title}");
        }

        $this->newLine();
        $this->comment('Deprecated entries will show warnings when retrieved.');

        return self::SUCCESS;
    }
}
