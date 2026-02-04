<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class KnowledgeShowCommand extends Command
{
    protected $signature = 'show {id : The ID of the knowledge entry to display}';

    protected $description = 'Display full details of a knowledge entry';

    public function handle(QdrantService $qdrant): int
    {
        $id = $this->argument('id');

        if (is_numeric($id)) {
            $id = (int) $id;
        }

        $entry = spin(
            fn (): ?array => $qdrant->getById($id),
            'Fetching entry...'
        );

        if (! $entry) {
            error('Entry not found.');

            return self::FAILURE;
        }

        $qdrant->incrementUsage($id);

        $this->renderEntry($entry);

        return self::SUCCESS;
    }

    private function renderEntry(array $entry): void
    {
        $this->newLine();
        $this->line("<fg=cyan;options=bold>{$entry['title']}</>");
        $this->line("<fg=gray>ID: {$entry['id']}</>");
        $this->newLine();

        $this->line($entry['content']);
        $this->newLine();

        // Metadata table
        $rows = [
            ['Category', $entry['category'] ?? 'N/A'],
            ['Priority', $this->colorize($entry['priority'], $this->priorityColor($entry['priority']))],
            ['Status', $this->colorize($entry['status'], $this->statusColor($entry['status']))],
            ['Confidence', $this->colorize("{$entry['confidence']}%", $this->confidenceColor($entry['confidence']))],
            ['Usage', (string) $entry['usage_count']],
        ];

        if ($entry['module']) {
            $rows[] = ['Module', $entry['module']];
        }

        if (! empty($entry['tags'])) {
            $rows[] = ['Tags', implode(', ', $entry['tags'])];
        }

        table(['Field', 'Value'], $rows);

        $this->newLine();
        $this->line("<fg=gray>Created: {$entry['created_at']} | Updated: {$entry['updated_at']}</>");
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
