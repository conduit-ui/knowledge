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
        $rawId = $this->argument('id');

        // Type narrowing for PHPStan
        if (! is_string($rawId) && ! is_int($rawId)) {
            error('Invalid ID provided.');

            return self::FAILURE;
        }

        $id = is_numeric($rawId) ? (int) $rawId : $rawId;

        $entry = spin(
            fn () => $qdrant->getById($id),
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

    /**
     * @param  array<string, mixed>  $entry
     */
    private function renderEntry(array $entry): void
    {
        // Extract and cast values
        $title = is_scalar($entry['title'] ?? null) ? (string) $entry['title'] : '';
        $id = is_scalar($entry['id'] ?? null) ? (string) $entry['id'] : '';
        $content = is_scalar($entry['content'] ?? null) ? (string) $entry['content'] : '';
        $category = is_scalar($entry['category'] ?? null) ? (string) $entry['category'] : 'N/A';
        $priority = is_scalar($entry['priority'] ?? null) ? (string) $entry['priority'] : 'medium';
        $status = is_scalar($entry['status'] ?? null) ? (string) $entry['status'] : 'draft';
        $confidence = is_int($entry['confidence'] ?? null) ? $entry['confidence'] : 0;
        $usageCount = is_scalar($entry['usage_count'] ?? null) ? (string) $entry['usage_count'] : '0';
        $module = is_scalar($entry['module'] ?? null) ? (string) $entry['module'] : null;
        $createdAt = is_scalar($entry['created_at'] ?? null) ? (string) $entry['created_at'] : '';
        $updatedAt = is_scalar($entry['updated_at'] ?? null) ? (string) $entry['updated_at'] : '';
        /** @var array<string> $tags */
        $tags = is_array($entry['tags'] ?? null) ? $entry['tags'] : [];

        $this->newLine();
        $this->line("<fg=cyan;options=bold>{$title}</>");
        $this->line("<fg=gray>ID: {$id}</>");
        $this->newLine();

        $this->line($content);
        $this->newLine();

        // Metadata table
        $rows = [
            ['Category', $category],
            ['Priority', $this->colorize($priority, $this->priorityColor($priority))],
            ['Status', $this->colorize($status, $this->statusColor($status))],
            ['Confidence', $this->colorize("{$confidence}%", $this->confidenceColor($confidence))],
            ['Usage', $usageCount],
        ];

        if ($module !== null) {
            $rows[] = ['Module', $module];
        }

        if (count($tags) > 0) {
            $rows[] = ['Tags', implode(', ', $tags)];
        }

        table(['Field', 'Value'], $rows);

        $this->newLine();
        $this->line("<fg=gray>Created: {$createdAt} | Updated: {$updatedAt}</>");
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
