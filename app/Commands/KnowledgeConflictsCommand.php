<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use App\Models\Relationship;
use LaravelZero\Framework\Commands\Command;

class KnowledgeConflictsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'conflicts
                            {--category= : Filter by category}
                            {--module= : Filter by module}';

    /**
     * @var string
     */
    protected $description = 'Detect entries with conflicting relationships or overlapping advice';

    public function handle(): int
    {
        $this->info('Scanning for conflicts...');
        $this->newLine();

        // Find explicit conflicts (existing relationships)
        $explicitConflicts = $this->findExplicitConflicts();

        // Find potential conflicts (same category/module but different advice)
        $potentialConflicts = $this->findPotentialConflicts();

        if ($explicitConflicts->isEmpty() && $potentialConflicts->isEmpty()) {
            $this->info('No conflicts found.');

            return self::SUCCESS;
        }

        if ($explicitConflicts->isNotEmpty()) {
            $this->displayExplicitConflicts($explicitConflicts);
        }

        if (! $potentialConflicts->isEmpty()) {
            $this->displayPotentialConflicts($potentialConflicts);
        }

        $this->newLine();
        $this->comment('Resolve conflicts by:');
        $this->comment('  - Deprecating outdated entries: knowledge:deprecate {id} --replacement={id}');
        $this->comment('  - Merging related entries: knowledge:merge {id1} {id2}');
        $this->comment('  - Adding clarification to both entries');

        return self::SUCCESS;
    }

    /**
     * Find entries with explicit conflicts_with relationships.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Relationship>
     */
    private function findExplicitConflicts(): \Illuminate\Database\Eloquent\Collection
    {
        $query = Relationship::query()
            ->where('type', Relationship::TYPE_CONFLICTS_WITH)
            ->with(['fromEntry', 'toEntry']);

        $category = $this->option('category');
        if ($category !== null) {
            $query->whereHas('fromEntry', function ($q) use ($category): void {
                $q->where('category', $category);
            });
        }

        $module = $this->option('module');
        if ($module !== null) {
            $query->whereHas('fromEntry', function ($q) use ($module): void {
                $q->where('module', $module);
            });
        }

        return $query->get();
    }

    /**
     * Find potential conflicts based on category/module overlap with similar topics.
     *
     * @return \Illuminate\Support\Collection<int, array{entry1: Entry, entry2: Entry, reason: string}>
     */
    private function findPotentialConflicts(): \Illuminate\Support\Collection
    {
        $conflicts = collect();

        $query = Entry::query()->where('status', '!=', 'deprecated');

        $category = $this->option('category');
        if ($category !== null) {
            $query->where('category', $category);
        }

        $module = $this->option('module');
        if ($module !== null) {
            $query->where('module', $module);
        }

        $entries = $query->get();

        // Group by category and module
        $grouped = $entries->groupBy(fn (Entry $e): string => ($e->category ?? 'none').':'.($e->module ?? 'none'));

        foreach ($grouped as $groupedEntries) {
            if ($groupedEntries->count() < 2) {
                continue;
            }

            // Find entries with "different" priority (potential conflicting recommendations)
            $critical = $groupedEntries->where('priority', 'critical');
            $low = $groupedEntries->where('priority', 'low');

            // Check if there are critical and low priority entries with overlapping topics
            foreach ($critical as $criticalEntry) {
                foreach ($low as $lowEntry) {
                    $overlap = $this->hasTopicOverlap($criticalEntry, $lowEntry);
                    if ($overlap) {
                        // Skip if already has explicit conflict relationship
                        /** @phpstan-ignore-next-line */
                        $hasExplicit = Relationship::query()
                            ->where('type', Relationship::TYPE_CONFLICTS_WITH)
                            ->where(function ($q) use ($criticalEntry, $lowEntry): void {
                                $q->where(function ($sub) use ($criticalEntry, $lowEntry): void {
                                    $sub->where('from_entry_id', $criticalEntry->id)
                                        ->where('to_entry_id', $lowEntry->id);
                                })->orWhere(function ($sub) use ($criticalEntry, $lowEntry): void {
                                    $sub->where('from_entry_id', $lowEntry->id)
                                        ->where('to_entry_id', $criticalEntry->id);
                                });
                            })
                            ->count() > 0;

                        if (! $hasExplicit) {
                            $conflicts->push([
                                'entry1' => $criticalEntry,
                                'entry2' => $lowEntry,
                                'reason' => 'Same category/module with conflicting priorities (critical vs low)',
                            ]);
                        }
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check if two entries have topic overlap based on title/tags.
     */
    private function hasTopicOverlap(Entry $a, Entry $b): bool
    {
        // Check tag overlap
        $tagsA = $a->tags ?? [];
        $tagsB = $b->tags ?? [];

        $commonTags = array_intersect($tagsA, $tagsB);
        if (count($commonTags) >= 2) {
            return true;
        }

        // Check title similarity (simple word overlap)
        $wordsA = array_filter(explode(' ', mb_strtolower($a->title)), fn ($w): bool => strlen($w) > 3);
        $wordsB = array_filter(explode(' ', mb_strtolower($b->title)), fn ($w): bool => strlen($w) > 3);

        $commonWords = array_intersect($wordsA, $wordsB);

        return count($commonWords) >= 2;
    }

    /**
     * Display explicit conflicts.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Relationship>  $conflicts
     */
    private function displayExplicitConflicts(\Illuminate\Database\Eloquent\Collection $conflicts): void
    {
        $this->warn("Found {$conflicts->count()} explicit ".str('conflict')->plural($conflicts->count()).'.');
        $this->newLine();

        foreach ($conflicts as $relationship) {
            /** @phpstan-ignore-next-line */
            $from = $relationship->fromEntry;
            /** @phpstan-ignore-next-line */
            $to = $relationship->toEntry;

            if ($from === null || $to === null) { // @codeCoverageIgnore
                continue; // @codeCoverageIgnore
            } // @codeCoverageIgnore

            $this->line('<options=bold>Conflict:</>');
            $this->line("  #{$from->id} {$from->title}");
            $this->line('  <fg=red>conflicts with</>');
            $this->line("  #{$to->id} {$to->title}");
            $this->newLine();
        }
    }

    /**
     * Display potential conflicts.
     *
     * @param  \Illuminate\Support\Collection<int, array{entry1: Entry, entry2: Entry, reason: string}>  $conflicts
     */
    private function displayPotentialConflicts(\Illuminate\Support\Collection $conflicts): void
    {
        $this->warn("Found {$conflicts->count()} potential ".str('conflict')->plural($conflicts->count()).'.');
        $this->newLine();

        foreach ($conflicts as $conflict) {
            $entry1 = $conflict['entry1'];
            $entry2 = $conflict['entry2'];

            $this->line('<options=bold>Potential Conflict:</>');
            $this->line("  #{$entry1->id} {$entry1->title} <fg=gray>[{$entry1->priority}]</>");
            $this->line("  #{$entry2->id} {$entry2->title} <fg=gray>[{$entry2->priority}]</>");
            $this->line("  <fg=yellow>Reason: {$conflict['reason']}</>");
            $this->newLine();
        }
    }
}
