<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use App\Services\SimilarityService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeDuplicatesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'duplicates
                            {--threshold=70 : Similarity threshold percentage (0-100)}
                            {--limit=10 : Maximum duplicate groups to show}';

    /**
     * @var string
     */
    protected $description = 'Find potentially duplicate entries based on title and content similarity';

    public function handle(SimilarityService $similarityService): int
    {
        $threshold = (int) $this->option('threshold');
        $limit = (int) $this->option('limit');

        if ($threshold < 0 || $threshold > 100) {
            $this->error('Threshold must be between 0 and 100.');

            return self::FAILURE;
        }

        $this->info('Scanning for duplicate entries...');
        $this->newLine();

        $entries = Entry::all();

        if ($entries->count() < 2) {
            $this->info('Not enough entries to compare (need at least 2).');

            return self::SUCCESS;
        }

        $duplicateGroups = $similarityService->findDuplicates($entries, $threshold / 100);

        if ($duplicateGroups->isEmpty()) {
            $this->info('No potential duplicates found above the threshold.');

            return self::SUCCESS;
        }

        $this->warn("Found {$duplicateGroups->count()} potential duplicate ".str('group')->plural($duplicateGroups->count()).'.');
        $this->newLine();

        $displayed = 0;
        foreach ($duplicateGroups as $group) {
            if ($displayed >= $limit) {
                $remaining = $duplicateGroups->count() - $limit;
                $this->comment("... and {$remaining} more ".str('group')->plural($remaining));
                break;
            }

            $this->displayDuplicateGroup($group);
            $displayed++;
        }

        $this->newLine();
        $this->comment('Use "knowledge:merge {id1} {id2}" to combine duplicate entries.');

        return self::SUCCESS;
    }

    /**
     * Display a group of duplicate entries.
     *
     * @param  array{entries: array<Entry>, similarity: float}  $group
     */
    private function displayDuplicateGroup(array $group): void
    {
        $similarityPercent = (int) round($group['similarity'] * 100);

        $this->line("<options=bold>Similarity: {$similarityPercent}%</>");

        foreach ($group['entries'] as $entry) {
            $this->line("  #{$entry->id} {$entry->title}");
            $this->line("     <fg=gray>Status: {$entry->status} | Confidence: {$entry->confidence}%</>");
        }

        $this->newLine();
    }
}
