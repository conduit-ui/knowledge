<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use App\Models\Relationship;
use App\Services\RelationshipService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeMergeCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'knowledge:merge
                            {primary : The ID of the primary entry (will be kept)}
                            {secondary : The ID of the secondary entry (will be deprecated)}
                            {--keep-both : Keep both entries but link them}';

    /**
     * @var string
     */
    protected $description = 'Merge two entries, combining their content and relationships';

    public function handle(RelationshipService $relationshipService): int
    {
        $primaryId = $this->argument('primary');
        $secondaryId = $this->argument('secondary');

        // Validate IDs
        if (! is_numeric($primaryId)) {
            $this->error('Primary ID must be a number.');

            return self::FAILURE;
        }

        if (! is_numeric($secondaryId)) {
            $this->error('Secondary ID must be a number.');

            return self::FAILURE;
        }

        if ((int) $primaryId === (int) $secondaryId) {
            $this->error('Cannot merge an entry with itself.');

            return self::FAILURE;
        }

        // Fetch entries
        /** @var Entry|null $primary */
        $primary = Entry::query()->find((int) $primaryId);

        if ($primary === null) {
            $this->error("Primary entry not found with ID: {$primaryId}");

            return self::FAILURE;
        }

        /** @var Entry|null $secondary */
        $secondary = Entry::query()->find((int) $secondaryId);

        if ($secondary === null) {
            $this->error("Secondary entry not found with ID: {$secondaryId}");

            return self::FAILURE;
        }

        $this->info('Merging entries...');
        $this->newLine();
        $this->line("Primary:   #{$primary->id} {$primary->title}");
        $this->line("Secondary: #{$secondary->id} {$secondary->title}");
        $this->newLine();

        /** @var bool $keepBoth */
        $keepBoth = (bool) $this->option('keep-both');

        if ($keepBoth) {
            // Just link them without merging content
            $relationshipService->createRelationship(
                $secondary->id,
                $primary->id,
                Relationship::TYPE_REPLACED_BY
            );

            $secondary->update(['status' => 'deprecated']);

            $this->info('Entries linked. Secondary entry deprecated.');
        } else {
            // Merge content and metadata
            $this->mergeEntries($primary, $secondary);

            // Transfer relationships from secondary to primary
            $this->transferRelationships($primary, $secondary, $relationshipService);

            // Deprecate secondary entry
            $relationshipService->createRelationship(
                $secondary->id,
                $primary->id,
                Relationship::TYPE_REPLACED_BY
            );

            $secondary->update([
                'status' => 'deprecated',
                'confidence' => 0,
            ]);

            $this->info('Entries merged successfully.');
        }

        $this->newLine();
        $this->line("Primary entry #{$primary->id} updated.");
        $this->line("Secondary entry #{$secondary->id} deprecated.");

        return self::SUCCESS;
    }

    /**
     * Merge content and metadata from secondary into primary.
     */
    private function mergeEntries(Entry $primary, Entry $secondary): void
    {
        // Merge tags
        $primaryTags = $primary->tags ?? [];
        $secondaryTags = $secondary->tags ?? [];
        $mergedTags = array_values(array_unique(array_merge($primaryTags, $secondaryTags)));

        // Merge files
        $primaryFiles = $primary->files ?? [];
        $secondaryFiles = $secondary->files ?? [];
        $mergedFiles = array_values(array_unique(array_merge($primaryFiles, $secondaryFiles)));

        // Use higher confidence
        $mergedConfidence = max($primary->confidence, $secondary->confidence);

        // Use higher priority
        $priorities = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        $primaryPriority = $priorities[$primary->priority] ?? 2;
        $secondaryPriority = $priorities[$secondary->priority] ?? 2;
        $mergedPriority = $primaryPriority >= $secondaryPriority ? $primary->priority : $secondary->priority;

        // Append content note
        $contentNote = "\n\n---\n*Merged from entry #{$secondary->id}*";

        $primary->update([
            'tags' => $mergedTags,
            'files' => $mergedFiles,
            'confidence' => $mergedConfidence,
            'priority' => $mergedPriority,
            'content' => $primary->content.$contentNote,
            'usage_count' => $primary->usage_count + $secondary->usage_count,
        ]);
    }

    /**
     * Transfer relationships from secondary entry to primary.
     */
    private function transferRelationships(Entry $primary, Entry $secondary, RelationshipService $relationshipService): void
    {
        // Get all relationships involving the secondary entry
        $outgoing = Relationship::query()
            ->where('from_entry_id', $secondary->id)
            ->where('type', '!=', Relationship::TYPE_REPLACED_BY)
            ->get();

        $incoming = Relationship::query()
            ->where('to_entry_id', $secondary->id)
            ->where('type', '!=', Relationship::TYPE_REPLACED_BY)
            ->get();

        $transferred = 0;

        // Transfer outgoing relationships
        foreach ($outgoing as $rel) {
            // Skip if target is the primary entry
            if ($rel->to_entry_id === $primary->id) {
                continue;
            }

            // Check if relationship already exists
            /** @phpstan-ignore-next-line */
            $existsCount = Relationship::query()
                ->where('from_entry_id', $primary->id)
                ->where('to_entry_id', $rel->to_entry_id)
                ->where('type', $rel->type)
                ->count();

            if ($existsCount === 0) {
                try {
                    $relationshipService->createRelationship(
                        $primary->id,
                        $rel->to_entry_id,
                        $rel->type,
                        $rel->metadata
                    );
                    $transferred++;
                    // @codeCoverageIgnoreStart
                } catch (\Throwable) {
                    // Skip if relationship can't be created
                }
                // @codeCoverageIgnoreEnd
            }
        }

        // Transfer incoming relationships
        foreach ($incoming as $rel) {
            // Skip if source is the primary entry
            if ($rel->from_entry_id === $primary->id) {
                continue;
            }

            // Check if relationship already exists
            /** @phpstan-ignore-next-line */
            $existsCount2 = Relationship::query()
                ->where('from_entry_id', $rel->from_entry_id)
                ->where('to_entry_id', $primary->id)
                ->where('type', $rel->type)
                ->count();

            if ($existsCount2 === 0) {
                try {
                    $relationshipService->createRelationship(
                        $rel->from_entry_id,
                        $primary->id,
                        $rel->type,
                        $rel->metadata
                    );
                    $transferred++;
                    // @codeCoverageIgnoreStart
                } catch (\Throwable) {
                    // Skip if relationship can't be created
                }
                // @codeCoverageIgnoreEnd
            }
        }

        if ($transferred > 0) {
            $this->line("Transferred {$transferred} ".str('relationship')->plural($transferred).'.');
        }
    }
}
