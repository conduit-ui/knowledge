<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\ResolvesProject;
use App\Services\EnhancementQueueService;
use App\Services\EntryMetadataService;
use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class KnowledgeShowCommand extends Command
{
    use ResolvesProject;

    protected $signature = 'show
                            {id : The ID of the knowledge entry to display}
                            {--project= : Override project namespace}
                            {--global : Search across all projects}';

    protected $description = 'Display full details of a knowledge entry';

    public function handle(QdrantService $qdrant, EntryMetadataService $metadata, EnhancementQueueService $enhancementQueue): int
    {
        $id = $this->argument('id');

        if (is_numeric($id)) {
            $id = (int) $id;
        }

        if (! is_string($id) && ! is_int($id)) { // @codeCoverageIgnoreStart
            error('Invalid entry ID.');

            return self::FAILURE;
        } // @codeCoverageIgnoreEnd

        $project = $this->resolveProject();

        $entry = spin(
            fn (): ?array => $qdrant->getById($id, $project),
            'Fetching entry...'
        );

        if (! $entry) {
            error('Entry not found.');

            return self::FAILURE;
        }

        $qdrant->incrementUsage($id, $project);

        $this->renderEntry($entry, $metadata, $enhancementQueue);

        // Show supersession history
        $history = $qdrant->getSupersessionHistory($id);
        $this->renderSupersessionHistory($entry, $history);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function renderEntry(array $entry, EntryMetadataService $metadata, EnhancementQueueService $enhancementQueue): void
    {
        $this->newLine();

        $titleLine = "<fg=cyan;options=bold>{$entry['title']}</>";
        $supersededBy = $entry['superseded_by'] ?? null;
        if ($supersededBy !== null && $supersededBy !== '') {
            $titleLine .= ' <fg=red>[SUPERSEDED]</>';
        }
        $this->line($titleLine);
        $this->line("<fg=gray>ID: {$entry['id']}</>");
        $this->newLine();

        // Staleness warning
        if ($metadata->isStale($entry)) {
            $days = $metadata->daysSinceVerification($entry);
            warning("This entry has not been verified in {$days} days and may be outdated.");
            $this->newLine();
        }

        $this->line($entry['content']);
        $this->newLine();

        $effectiveConfidence = $metadata->calculateEffectiveConfidence($entry);
        $confidenceLevel = $metadata->confidenceLevel($effectiveConfidence);

        // Metadata table
        $rows = [
            ['Category', $entry['category'] ?? 'N/A'],
            ['Priority', $this->colorize($entry['priority'], $this->priorityColor($entry['priority']))],
            ['Status', $this->colorize($entry['status'], $this->statusColor($entry['status']))],
            ['Confidence', $this->colorize("{$effectiveConfidence}% ({$confidenceLevel})", $this->confidenceColor($effectiveConfidence))],
            ['Usage', (string) $entry['usage_count']],
            ['Last Verified', $entry['last_verified'] ?? 'Never'],
            ['Evidence', $entry['evidence'] ?? 'N/A'],
        ];

        if ($entry['module'] !== null) {
            $rows[] = ['Module', $entry['module']];
        }

        /** @var array<string> $tags */
        $tags = $entry['tags'] ?? [];
        if ($tags !== []) {
            $rows[] = ['Tags', implode(', ', $tags)];
        }

        if ($supersededBy !== null && $supersededBy !== '') {
            $rows[] = ['Superseded By', $supersededBy];
            $rows[] = ['Superseded Date', $entry['superseded_date'] ?? 'N/A'];
            $rows[] = ['Superseded Reason', $entry['superseded_reason'] ?? 'N/A'];
        }

        // Enhancement status
        $rows[] = ['Enhanced', $this->enhancementStatus($entry, $enhancementQueue)];

        if (isset($entry['concepts']) && $entry['concepts'] !== []) {
            $rows[] = ['Concepts', implode(', ', $entry['concepts'])];
        }

        if (isset($entry['summary']) && $entry['summary'] !== '') {
            $rows[] = ['AI Summary', $entry['summary']];
        }

        table(['Field', 'Value'], $rows);

        $this->newLine();
        $this->line("<fg=gray>Created: {$entry['created_at']} | Updated: {$entry['updated_at']}</>");
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array{supersedes: array<int, array<string, mixed>>, superseded_by: array<string, mixed>|null}  $history
     */
    private function renderSupersessionHistory(array $entry, array $history): void
    {
        $hasHistory = $history['supersedes'] !== [] || $history['superseded_by'] !== null;

        if (! $hasHistory) {
            return;
        }

        $this->newLine();
        $this->line('<fg=yellow;options=bold>Supersession History</>');

        if ($history['superseded_by'] !== null) {
            $successor = $history['superseded_by'];
            $this->line('<fg=red>  This entry was superseded by:</>');
            $this->line("    <fg=cyan>[{$successor['id']}]</> {$successor['title']}");
        }

        if ($history['supersedes'] !== []) {
            $this->line('<fg=green>  This entry supersedes:</>');
            foreach ($history['supersedes'] as $predecessor) {
                $reason = $predecessor['superseded_reason'] ?? 'No reason provided';
                $this->line("    <fg=cyan>[{$predecessor['id']}]</> {$predecessor['title']} <fg=gray>({$reason})</>");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function enhancementStatus(array $entry, EnhancementQueueService $enhancementQueue): string
    {
        if (isset($entry['enhanced']) && $entry['enhanced'] === true) {
            $enhancedAt = $entry['enhanced_at'] ?? 'Unknown';

            return "<fg=green>Yes</> <fg=gray>({$enhancedAt})</>";
        }

        if ($enhancementQueue->isQueued($entry['id'])) {
            return '<fg=yellow>Pending</>';
        }

        return '<fg=gray>No</>';
    }

    private function colorize(string $text, string $color): string
    {
        return "<fg={$color}>{$text}</>";
    }

    /**
     * @codeCoverageIgnore UI helper - match branches for edge cases
     */
    private function priorityColor(string $priority): string
    {
        return match ($priority) {
            'critical' => 'red',
            'high' => 'yellow',
            'medium' => 'white',
            default => 'gray',
        };
    }

    /**
     * @codeCoverageIgnore UI helper - match branches for edge cases
     */
    private function statusColor(string $status): string
    {
        return match ($status) {
            'validated' => 'green',
            'deprecated' => 'red',
            default => 'yellow',
        };
    }

    private function confidenceColor(int $confidence): string
    {
        return match (true) {
            $confidence >= 80 => 'green',
            $confidence >= 50 => 'yellow',
            default => 'red',
        };
    }
}
