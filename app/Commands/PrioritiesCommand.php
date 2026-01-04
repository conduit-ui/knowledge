<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;

class PrioritiesCommand extends Command
{
    protected $signature = 'priorities
                            {--project= : Filter by project/module}';

    protected $description = 'Show top 3 priorities based on blockers, intents, and confidence';

    public function handle(): int
    {
        /** @var string|null $project */
        $project = $this->option('project');

        $entries = $this->getEntries($project);

        if ($entries->isEmpty()) {
            $this->info('No priorities found');

            return self::SUCCESS;
        }

        $priorities = $this->calculatePriorities($entries);

        $this->displayPriorities($priorities, $project);

        return self::SUCCESS;
    }

    /**
     * @return EloquentCollection<int, Entry>
     */
    private function getEntries(?string $project): EloquentCollection
    {
        $query = Entry::query();

        if ($project !== null) {
            $query->where(function ($q) use ($project) {
                $q->where('tags', 'like', "%{$project}%")
                    ->orWhere('module', $project);
            });
        }

        return $query->get();
    }

    /**
     * @param  EloquentCollection<int, Entry>  $entries
     * @return Collection<int, array{entry: Entry, score: float, reasons: array<int, string>}>
     */
    private function calculatePriorities(EloquentCollection $entries): Collection
    {
        // Filter out entries that have blocker indicators but are resolved/deprecated
        $filtered = $entries->filter(function (Entry $entry) {
            // Check if entry has blocker indicators
            $hasBlockerIndicator = false;

            if ($entry->tags !== null) {
                $tags = is_array($entry->tags) ? $entry->tags : json_decode($entry->tags, true);
                if (is_array($tags) && (in_array('blocker', $tags, true) || in_array('blocked', $tags, true))) {
                    $hasBlockerIndicator = true;
                }
            }

            $content = $entry->content ?? '';
            if (str_contains($content, '## Blockers') || str_contains($content, 'Blocker:')) {
                $hasBlockerIndicator = true;
            }

            // If it has blocker indicators and is resolved/deprecated, exclude it
            if ($hasBlockerIndicator && in_array($entry->status, ['validated', 'deprecated'], true)) {
                return false;
            }

            return true;
        });

        $scored = $filtered->map(function (Entry $entry) {
            $score = $this->calculateScore($entry);
            $reasons = $this->determineReasons($entry);

            return [
                'entry' => $entry,
                'score' => $score,
                'reasons' => $reasons,
            ];
        });

        // Sort by score descending, then by created_at descending for ties
        return $scored->sortBy([
            fn ($a, $b) => $b['score'] <=> $a['score'],
            fn ($a, $b) => $b['entry']->created_at <=> $a['entry']->created_at,
        ])->take(3);
    }

    private function calculateScore(Entry $entry): float
    {
        $blockerWeight = $this->isBlocker($entry) ? 1 : 0;
        $intentRecency = $this->calculateIntentRecency($entry);
        $confidence = $entry->confidence ?? 0;

        // Scoring formula: (blocker_weight × 3) + (intent_recency × 2) + (confidence × 1)
        return ($blockerWeight * 3) + ($intentRecency * 2) + ($confidence * 1);
    }

    private function isBlocker(Entry $entry): bool
    {
        // Exclude resolved/deprecated blockers
        if (in_array($entry->status, ['validated', 'deprecated'], true)) {
            return false;
        }

        // Check tags
        if ($entry->tags !== null) {
            $tags = is_array($entry->tags) ? $entry->tags : json_decode($entry->tags, true);
            if (is_array($tags) && (in_array('blocker', $tags, true) || in_array('blocked', $tags, true))) {
                return true;
            }
        }

        // Check content patterns
        $content = $entry->content ?? '';
        if (str_contains($content, '## Blockers') || str_contains($content, 'Blocker:')) {
            return true;
        }

        return false;
    }

    private function calculateIntentRecency(Entry $entry): float
    {
        // Check if entry has user-intent tag
        $hasIntent = false;
        if ($entry->tags !== null) {
            $tags = is_array($entry->tags) ? $entry->tags : json_decode($entry->tags, true);
            if (is_array($tags) && in_array('user-intent', $tags, true)) {
                $hasIntent = true;
            }
        }

        if (! $hasIntent) {
            return 0;
        }

        // Calculate recency score (0-100 scale, decaying with age)
        $ageInDays = (int) $entry->created_at->diffInDays(now());

        // Recent items get higher scores
        // 0 days = 100, 1 day = 95, 7 days = 65, 30 days = 20, 60+ days = 0
        if ($ageInDays === 0) {
            return 100;
        }

        if ($ageInDays === 1) {
            return 95;
        }

        if ($ageInDays <= 7) {
            return 100 - ($ageInDays * 5);
        }

        if ($ageInDays <= 30) {
            return 65 - (($ageInDays - 7) * 2);
        }

        if ($ageInDays <= 60) {
            return max(0, 20 - (($ageInDays - 30) * 0.67));
        }

        return 0;
    }

    /**
     * @return array<int, string>
     */
    private function determineReasons(Entry $entry): array
    {
        $reasons = [];

        if ($this->isBlocker($entry)) {
            $reasons[] = 'Blocker';
        }

        // Check if recent intent
        $hasIntent = false;
        if ($entry->tags !== null) {
            $tags = is_array($entry->tags) ? $entry->tags : json_decode($entry->tags, true);
            if (is_array($tags) && in_array('user-intent', $tags, true)) {
                $hasIntent = true;
            }
        }

        if ($hasIntent) {
            $ageInDays = (int) $entry->created_at->diffInDays(now());
            if ($ageInDays <= 7) {
                $reasons[] = 'Recent user intent';
            }
        }

        // Check high confidence
        if ($entry->confidence !== null && $entry->confidence >= 80) {
            $reasons[] = 'High confidence';
        }

        // Check priority level
        if ($entry->priority === 'critical') {
            $reasons[] = 'Critical priority';
        } elseif ($entry->priority === 'high') {
            $reasons[] = 'High priority';
        }

        if ($reasons === []) {
            $reasons[] = 'Confidence score';
        }

        return $reasons;
    }

    /**
     * @param  Collection<int, array{entry: Entry, score: float, reasons: array<int, string>}>  $priorities
     */
    private function displayPriorities(Collection $priorities, ?string $project): void
    {
        $this->info('Top 3 Priorities');

        if ($project !== null) {
            $this->line("Project: {$project}");
        }

        $this->newLine();

        $rank = 1;
        foreach ($priorities as $priority) {
            $this->displayPriority($priority, $rank);
            $this->newLine();
            $rank++;
        }
    }

    /**
     * @param  array{entry: Entry, score: float, reasons: array<int, string>}  $priority
     */
    private function displayPriority(array $priority, int $rank): void
    {
        $entry = $priority['entry'];
        $score = $priority['score'];
        $reasons = $priority['reasons'];

        $this->line("<options=bold>#{$rank}</>");
        $this->line("<options=bold>ID: {$entry->id}</>");
        $this->line("Title: {$entry->title}");
        $this->line(sprintf('Score: %.2f', $score));

        if ($entry->category !== null) {
            $this->line("Category: {$entry->category}");
        }

        if ($entry->priority !== null) {
            $this->line("Priority: {$entry->priority}");
        }

        $confidence = $entry->confidence ?? 0;
        $this->line("Confidence: {$confidence}");

        $ageInDays = (int) $entry->created_at->diffInDays(now());
        $this->line("Age: {$ageInDays} days");

        $this->line('Reason: '.implode(', ', $reasons));
    }
}
