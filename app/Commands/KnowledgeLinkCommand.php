<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use App\Models\Relationship;
use App\Services\RelationshipService;
use LaravelZero\Framework\Commands\Command;

/**
 * Create a relationship between two knowledge entries.
 */
class KnowledgeLinkCommand extends Command
{
    protected $signature = 'knowledge:link
                            {from : The ID of the source entry}
                            {to : The ID of the target entry}
                            {--type=relates_to : The relationship type}
                            {--bidirectional : Create bidirectional relationship}
                            {--metadata= : JSON metadata for the relationship}';

    protected $description = 'Create a relationship between two knowledge entries';

    public function handle(RelationshipService $service): int
    {
        $fromId = (int) $this->argument('from');
        $toId = (int) $this->argument('to');
        $type = $this->option('type');
        $bidirectional = $this->option('bidirectional');
        $metadataJson = $this->option('metadata');

        // Validate type
        if (! in_array($type, Relationship::types(), true)) {
            $this->error("Invalid relationship type: {$type}");
            $this->line('');
            $this->line('Valid types:');
            foreach (Relationship::types() as $validType) {
                $this->line("  - {$validType}");
            }

            return self::FAILURE;
        }

        // Validate entries exist
        $fromEntry = Entry::find($fromId);
        if (! $fromEntry) {
            $this->error("Entry {$fromId} not found");

            return self::FAILURE;
        }

        $toEntry = Entry::find($toId);
        if (! $toEntry) {
            $this->error("Entry {$toId} not found");

            return self::FAILURE;
        }

        // Parse metadata
        $metadata = null;
        if ($metadataJson) {
            $metadata = json_decode($metadataJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON metadata: '.json_last_error_msg());

                return self::FAILURE;
            }
        }

        try {
            if ($bidirectional) {
                [$rel1, $rel2] = $service->createBidirectionalRelationship($fromId, $toId, $type, $metadata);
                $this->info("Created bidirectional {$type} relationship");
                $this->line("  Forward:  #{$rel1->id} ({$fromEntry->title} → {$toEntry->title})");
                $this->line("  Backward: #{$rel2->id} ({$toEntry->title} → {$fromEntry->title})");
            } else {
                $relationship = $service->createRelationship($fromId, $toId, $type, $metadata);
                $this->info("Created {$type} relationship #{$relationship->id}");
                $this->line("  From: #{$fromId} {$fromEntry->title}");
                $this->line("  To:   #{$toId} {$toEntry->title}");
            }

            if ($metadata) {
                $this->line('');
                $this->line('Metadata:');
                foreach ($metadata as $key => $value) {
                    $this->line("  {$key}: {$value}");
                }
            }

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
