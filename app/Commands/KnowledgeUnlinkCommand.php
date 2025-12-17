<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Relationship;
use App\Services\RelationshipService;
use LaravelZero\Framework\Commands\Command;

/**
 * Delete a relationship between knowledge entries.
 */
class KnowledgeUnlinkCommand extends Command
{
    protected $signature = 'unlink {id : The relationship ID to delete}';

    protected $description = 'Delete a relationship between knowledge entries';

    public function handle(RelationshipService $service): int
    {
        $relationshipId = (int) $this->argument('id');

        // Load relationship before deletion to show details
        $relationship = Relationship::with(['fromEntry', 'toEntry'])->find($relationshipId);

        if ($relationship === null) {
            $this->error("Relationship #{$relationshipId} not found");

            return self::FAILURE;
        }

        // Show relationship details
        $this->line("Deleting {$relationship->type} relationship:");
        $this->line("  From: #{$relationship->from_entry_id} {$relationship->fromEntry?->title}");
        $this->line("  To:   #{$relationship->to_entry_id} {$relationship->toEntry?->title}");

        if ($this->confirm('Are you sure you want to delete this relationship?', true)) {
            if ($service->deleteRelationship($relationshipId)) {
                $this->info('Relationship deleted successfully');

                return self::SUCCESS;
            } else {
                $this->error('Failed to delete relationship');

                return self::FAILURE;
            }
        }

        $this->line('Deletion cancelled');

        return self::SUCCESS;
    }
}
