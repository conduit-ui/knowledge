<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use Illuminate\Database\Eloquent\Collection;
use LaravelZero\Framework\Commands\Command;

class BlockersCommand extends Command
{
    protected $signature = 'blockers
                            {--resolved : Show resolved blockers instead of active ones}
                            {--project= : Filter by project/module}';

    protected $description = 'Show current obstacles and resolution patterns';

    public function handle(): int
    {
        $showResolved = $this->option('resolved');
        $project = $this->option('project');

        $blockers = $this->getBlockers($showResolved, $project);

        if ($blockers->isEmpty()) {
            $message = $showResolved ? 'No resolved blockers found' : 'No blockers found';
            $this->info($message);

            return self::SUCCESS;
        }

        $this->displayBlockers($blockers, $showResolved);

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Entry>
     */
    private function getBlockers(bool $showResolved, ?string $project): Collection
    {
        $query = Entry::query()
            ->where(function ($query) {
                $query->where('content', 'like', '%## Blockers%')
                    ->orWhere('content', 'like', '%Blocker:%')
                    ->orWhere('tags', 'like', '%blocker%')
                    ->orWhere('tags', 'like', '%blocked%');
            });

        if ($project !== null) {
            $query->where(function ($q) use ($project) {
                $q->where('tags', 'like', "%{$project}%")
                    ->orWhere('module', $project);
            });
        }

        if ($showResolved) {
            $query->where('status', 'validated');
        } else {
            $query->where('status', '!=', 'validated');
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * @param  Collection<int, Entry>  $blockers
     */
    private function displayBlockers(Collection $blockers, bool $showResolved): void
    {
        $count = $blockers->count();
        $plural = $count === 1 ? '' : 's';
        $status = $showResolved ? 'resolved' : 'unresolved';

        $this->info("Found {$count} {$status} blocker{$plural}:");
        $this->newLine();

        if (! $showResolved) {
            // Group by age for active blockers
            $grouped = $this->groupByAge($blockers);

            foreach ($grouped as $ageGroup => $groupedBlockers) {
                $this->components->info($ageGroup);
                foreach ($groupedBlockers as $blocker) {
                    $this->displayBlocker($blocker, $showResolved);
                    $this->newLine();
                }
            }
        } else {
            // Just list resolved blockers with patterns
            foreach ($blockers as $blocker) {
                $this->displayBlocker($blocker, $showResolved);
                $this->newLine();
            }
        }
    }

    /**
     * @param  Collection<int, Entry>  $blockers
     * @return array<string, Collection<int, Entry>>
     */
    private function groupByAge(Collection $blockers): array
    {
        $today = collect();
        $thisWeek = collect();
        $older = collect();

        foreach ($blockers as $blocker) {
            $age = (int) $blocker->created_at->diffInDays(now());

            if ($age === 0) {
                $today->push($blocker);
            } elseif ($age <= 7) {
                $thisWeek->push($blocker);
            } else {
                $older->push($blocker);
            }
        }

        $groups = [];

        if ($today->isNotEmpty()) {
            $groups['Today'] = $today;
        }

        if ($thisWeek->isNotEmpty()) {
            $groups['This Week'] = $thisWeek;
        }

        if ($older->isNotEmpty()) {
            $groups['>1 Week'] = $older;
        }

        return $groups;
    }

    private function displayBlocker(Entry $blocker, bool $showResolved): void
    {
        $age = (int) $blocker->created_at->diffInDays(now());
        $ageText = "{$age} days";

        $this->line("<options=bold>ID: {$blocker->id}</>");
        $this->line("Title: {$blocker->title}");
        $this->line("Status: {$blocker->status}");

        if ($blocker->category !== null) {
            $this->line("Category: {$blocker->category}");
        }

        // Highlight long-standing blockers (>7 days) in red
        if (! $showResolved && $age > 7) {
            $this->line("<fg=red>Age: {$ageText} (LONG STANDING)</>");
        } else {
            $this->line("Age: {$ageText}");
        }

        // Extract and display resolution patterns if available
        if ($showResolved) {
            $patterns = $this->extractResolutionPatterns($blocker);
            if (! empty($patterns)) {
                $this->line('<fg=green>Pattern:</>');
                foreach ($patterns as $pattern) {
                    $this->line("  • {$pattern}");
                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractResolutionPatterns(Entry $blocker): array
    {
        $patterns = [];
        $content = $blocker->content;

        // Look for "Pattern:" lines in Blockers Resolved section
        if (preg_match('/## Blockers Resolved(.+?)(?=##|$)/s', $content, $matches)) {
            $blockersSection = $matches[1];

            // Extract pattern lines
            if (preg_match_all('/[-•]\s*Pattern:\s*(.+?)(?=\n|$)/i', $blockersSection, $patternMatches)) {
                foreach ($patternMatches[1] as $pattern) {
                    $patterns[] = trim($pattern);
                }
            }

            // Also extract full resolution descriptions if no explicit patterns
            if (empty($patterns)) {
                if (preg_match_all('/[-•]\s*(.+?)(?=\n[-•]|\n\n|$)/s', $blockersSection, $descMatches)) {
                    foreach ($descMatches[1] as $desc) {
                        $cleaned = trim($desc);
                        if (! empty($cleaned) && ! str_starts_with($cleaned, 'Pattern:')) {
                            $patterns[] = $cleaned;
                        }
                    }
                }
            }
        }

        return $patterns;
    }
}
