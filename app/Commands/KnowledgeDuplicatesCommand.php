<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use Illuminate\Support\Collection;
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

    public function handle(): int
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

        $duplicateGroups = $this->findDuplicates($entries, $threshold / 100);

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
     * Find duplicate entries based on similarity.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Entry>  $entries
     * @return Collection<int, array{entries: array<Entry>, similarity: float}>
     */
    private function findDuplicates(\Illuminate\Database\Eloquent\Collection $entries, float $threshold): Collection
    {
        $duplicates = collect();
        $processed = [];

        foreach ($entries as $entry) {
            if (in_array($entry->id, $processed, true)) {
                continue;
            }

            $group = ['entries' => [$entry], 'similarity' => 1.0];

            foreach ($entries as $other) {
                if ($entry->id === $other->id || in_array($other->id, $processed, true)) {
                    continue;
                }

                $similarity = $this->calculateSimilarity($entry, $other);

                if ($similarity >= $threshold) {
                    $group['entries'][] = $other;
                    $group['similarity'] = min($group['similarity'], $similarity);
                    $processed[] = $other->id;
                }
            }

            if (count($group['entries']) > 1) {
                $duplicates->push($group);
                $processed[] = $entry->id;
            }
        }

        return $duplicates->sortByDesc('similarity')->values();
    }

    /**
     * Calculate similarity between two entries using Jaccard similarity.
     */
    private function calculateSimilarity(Entry $a, Entry $b): float
    {
        // Combine title and content for comparison
        $textA = mb_strtolower($a->title.' '.$a->content);
        $textB = mb_strtolower($b->title.' '.$b->content);

        // Tokenize into words
        $wordsA = $this->tokenize($textA);
        $wordsB = $this->tokenize($textB);

        if (count($wordsA) === 0 && count($wordsB) === 0) {
            return 0.0;
        }

        // Calculate Jaccard similarity
        $intersection = count(array_intersect($wordsA, $wordsB));
        $union = count(array_unique(array_merge($wordsA, $wordsB)));

        if ($union === 0) { // @codeCoverageIgnore
            return 0.0; // @codeCoverageIgnore
        } // @codeCoverageIgnore

        return $intersection / $union;
    }

    /**
     * Tokenize text into words.
     *
     * @return array<string>
     */
    private function tokenize(string $text): array
    {
        // Remove common stop words and tokenize
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'is', 'it', 'this', 'that'];
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false) { // @codeCoverageIgnore
            return []; // @codeCoverageIgnore
        } // @codeCoverageIgnore

        return array_values(array_filter(
            array_map(fn (string $word): string => preg_replace('/[^a-z0-9]/', '', $word) ?? '', $words),
            fn (string $word): bool => strlen($word) > 2 && ! in_array($word, $stopWords, true)
        ));
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
