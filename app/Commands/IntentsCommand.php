<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;

class IntentsCommand extends Command
{
    protected $signature = 'intents
                            {--limit=10 : Maximum number of intents to display}
                            {--project= : Filter by project tag}
                            {--since= : Show intents since date}
                            {--full : Show full content instead of compact titles}';

    protected $description = 'List user intents with filtering and date grouping';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        /** @var string|null $project */
        $project = $this->option('project');
        /** @var string|null $since */
        $since = $this->option('since');
        $full = (bool) $this->option('full');

        $intents = $this->getIntents($limit, $project, $since);
        $this->displayIntents($intents, $full);

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Entry>
     */
    private function getIntents(int $limit, ?string $project, ?string $since): Collection
    {
        /** @var Builder<Entry> $query */
        $query = Entry::query();

        /** @phpstan-ignore-next-line */
        $query->whereJsonContains('tags', 'user-intent')
            /** @phpstan-ignore-next-line */
            ->when($project !== null, function (Builder $q) use ($project): void {
                /** @phpstan-ignore-next-line */
                $q->whereJsonContains('tags', $project);
            })
            ->when($since !== null, function (Builder $q) use ($since): void {
                $date = Carbon::parse($since);
                $q->where('created_at', '>=', $date);
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        /** @var Collection<int, Entry> */
        return $query->get();
    }

    /**
     * @param  Collection<int, Entry>  $intents
     */
    private function displayIntents(Collection $intents, bool $full): void
    {
        if ($intents->isEmpty()) {
            $this->info('No user intents found');

            return;
        }

        $count = $intents->count();
        $this->info("Found {$count} user intent".($count === 1 ? '' : 's').':');
        $this->newLine();

        if (! $full) {
            // Date-grouped compact view
            $grouped = $this->groupByDate($intents);

            foreach ($grouped as $dateLabel => $groupedIntents) {
                $this->line("{$dateLabel}:");
                foreach ($groupedIntents as $intent) {
                    $this->displayCompactIntent($intent);
                }
                $this->newLine();
            }
        } else {
            // Full content view (no grouping for clarity)
            foreach ($intents as $intent) {
                $this->displayFullIntent($intent);
                $this->newLine();
            }
        }
    }

    /**
     * @param  Collection<int, Entry>  $intents
     * @return array<string, Collection<int, Entry>>
     */
    private function groupByDate(Collection $intents): array
    {
        $today = collect();
        $thisWeek = collect();
        $thisMonth = collect();
        $older = collect();

        foreach ($intents as $intent) {
            $age = (int) $intent->created_at->diffInDays(now());

            if ($age === 0) {
                $today->push($intent);
            } elseif ($age <= 7) {
                $thisWeek->push($intent);
            } elseif ($age <= 30) {
                $thisMonth->push($intent);
            } else {
                $older->push($intent);
            }
        }

        $groups = [];

        if ($today->isNotEmpty()) {
            $groups['Today'] = $today;
        }

        if ($thisWeek->isNotEmpty()) {
            $groups['This Week'] = $thisWeek;
        }

        if ($thisMonth->isNotEmpty()) {
            $groups['This Month'] = $thisMonth;
        }

        if ($older->isNotEmpty()) {
            $groups['Older'] = $older;
        }

        return $groups;
    }

    private function displayCompactIntent(Entry $intent): void
    {
        $age = (int) $intent->created_at->diffInDays(now());
        $ageText = $age === 0 ? 'today' : "{$age} days ago";

        $this->line("[{$intent->id}] {$intent->title}");

        $metadata = [];
        $metadata[] = $ageText;

        if ($intent->module !== null) {
            $metadata[] = "Module: {$intent->module}";
        }

        if ($intent->tags !== null && count($intent->tags) > 1) {
            // Show non-user-intent tags
            $otherTags = array_diff($intent->tags, ['user-intent']);
            if ($otherTags !== []) {
                $metadata[] = 'Tags: '.implode(', ', $otherTags);
            }
        }

        $this->line('  '.implode(' | ', $metadata));
    }

    private function displayFullIntent(Entry $intent): void
    {
        $this->line("<options=bold>ID: {$intent->id}</>");
        $this->line("Title: {$intent->title}");
        $this->line("Created: {$intent->created_at->format('Y-m-d H:i:s')}");

        if ($intent->module !== null) {
            $this->line("Module: {$intent->module}");
        }

        if ($intent->tags !== null) {
            $this->line('Tags: '.implode(', ', $intent->tags));
        }

        $this->newLine();
        $this->line('<fg=green>Content:</>');
        $this->line($intent->content);
        $this->line(str_repeat('─', 80));
    }
}
