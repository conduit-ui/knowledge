<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\MarkdownExporter;
use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeExportAllCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'export:all
                            {--format=markdown : Export format (markdown, json)}
                            {--output=./docs : Output directory path}
                            {--category= : Export only entries from a specific category}';

    /**
     * @var string
     */
    protected $description = 'Export all knowledge entries';

    public function handle(MarkdownExporter $markdownExporter, QdrantService $qdrant): int
    {
        /** @var string $format */
        $format = $this->option('format') ?? 'markdown';
        /** @var string $output */
        $output = $this->option('output') ?? './docs';
        /** @var string|null $category */
        $category = $this->option('category');

        // Build filters for Qdrant
        $filters = [];
        if ($category !== null) {
            $filters['category'] = $category;
        }

        // Get all entries (use high limit)
        $entries = $qdrant->search('', $filters, 10000);

        if ($entries->isEmpty()) {
            $this->warn('No entries found to export.');

            return self::SUCCESS;
        }

        // Create output directory
        if (! is_dir($output)) {
            mkdir($output, 0755, true);
        }

        $this->info("Exporting {$entries->count()} entries to: {$output}");

        $progressBar = $this->output->createProgressBar($entries->count());
        $progressBar->start();

        foreach ($entries as $entry) {
            $filename = $this->generateFilename($entry, $format);
            $filepath = "{$output}/{$filename}";

            $content = match ($format) {
                'markdown' => $markdownExporter->exportArray($entry),
                'json' => json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                // @codeCoverageIgnoreStart
                default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
                // @codeCoverageIgnoreEnd
            };

            file_put_contents($filepath, $content);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info('Export completed successfully!');

        return self::SUCCESS;
    }

    /**
     * Generate a filename for an entry.
     *
     * @param  array<string, mixed>  $entry
     */
    private function generateFilename(array $entry, string $format): string
    {
        $extension = $format === 'json' ? 'json' : 'md';
        $title = is_scalar($entry['title']) ? (string) $entry['title'] : 'untitled';
        $slug = $this->slugify($title);
        $id = is_scalar($entry['id']) ? (string) $entry['id'] : '0';

        return "{$id}-{$slug}.{$extension}";
    }

    /**
     * Convert a string to a slug.
     */
    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? $text;
        $converted = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = is_string($converted) ? $converted : $text;
        $text = preg_replace('~[^-\w]+~', '', $text) ?? $text;
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text) ?? $text;
        $text = strtolower($text);

        // @codeCoverageIgnoreStart
        // Defensive fallback for edge cases with special characters
        if ($text === '') {
            return 'untitled';
        }
        // @codeCoverageIgnoreEnd

        return substr($text, 0, 50);
    }
}
