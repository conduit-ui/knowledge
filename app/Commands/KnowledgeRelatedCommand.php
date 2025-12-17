<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use App\Services\RelationshipService;
use LaravelZero\Framework\Commands\Command;

/**
 * Show all relationships for a knowledge entry.
 */
class KnowledgeRelatedCommand extends Command
{
    protected $signature = 'related
                            {id : The entry ID}
                            {--suggest : Show suggested related entries}';

    protected $description = 'Show all relationships for a knowledge entry';

    public function handle(RelationshipService $service): int
    {
        $entryId = (int) $this->argument('id');

        $entry = Entry::find($entryId);
        if (! $entry) {
            $this->error("Entry #{$entryId} not found");

            return self::FAILURE;
        }

        $this->info("Relationships for: {$entry->title}");
        $this->line('');

        $grouped = $service->getGroupedRelationships($entryId);

        // Show outgoing relationships
        $this->line('<fg=cyan>Outgoing Relationships:</fg=cyan>');
        if (empty($grouped['outgoing'])) {
            $this->line('  None');
        } else {
            foreach ($grouped['outgoing'] as $type => $relationships) {
                $this->line("  <fg=yellow>{$type}</>:");
                foreach ($relationships as $rel) {
                    $this->line("    #{$rel->id} → #{$rel->to_entry_id} {$rel->toEntry->title}");
                    if (! empty($rel->metadata)) {
                        $this->line('      Metadata: '.json_encode($rel->metadata));
                    }
                }
            }
        }

        $this->line('');

        // Show incoming relationships
        $this->line('<fg=cyan>Incoming Relationships:</fg=cyan>');
        if (empty($grouped['incoming'])) {
            $this->line('  None');
        } else {
            foreach ($grouped['incoming'] as $type => $relationships) {
                $this->line("  <fg=yellow>{$type}</>:");
                foreach ($relationships as $rel) {
                    $this->line("    #{$rel->id} ← #{$rel->from_entry_id} {$rel->fromEntry->title}");
                    if (! empty($rel->metadata)) {
                        $this->line('      Metadata: '.json_encode($rel->metadata));
                    }
                }
            }
        }

        // Show suggestions if requested
        if ($this->option('suggest')) {
            $this->line('');
            $this->line('<fg=cyan>Suggested Related Entries:</fg=cyan>');
            $suggestions = $service->suggestRelatedEntries($entryId);

            if ($suggestions->isEmpty()) {
                $this->line('  No suggestions available');
            } else {
                foreach ($suggestions as $suggestion) {
                    $this->line("  #{$suggestion['entry']->id} {$suggestion['entry']->title}");
                    $this->line("    Score: {$suggestion['score']} - {$suggestion['reason']}");
                }
            }
        }

        return self::SUCCESS;
    }
}
