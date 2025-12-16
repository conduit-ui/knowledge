<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Collection;
use App\Models\Entry;
use App\Services\MarkdownExporter;
use LaravelZero\Framework\Commands\Command;

class KnowledgeExportAllCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'knowledge:export:all
                            {--format=markdown : Export format (markdown, json)}
                            {--output=./docs : Output directory path}
                            {--collection= : Export only entries from a specific collection}
                            {--category= : Export only entries from a specific category}';

    /**
     * @var string
     */
    protected $description = 'Export all knowledge entries';

    public function handle(MarkdownExporter $markdownExporter): int
    {
        /** @var string $format */
        $format = $this->option('format') ?? 'markdown';
        /** @var string $output */
        $output = $this->option('output') ?? './docs';
        /** @var string|null $collectionName */
        $collectionName = $this->option('collection');
        /** @var string|null $category */
        $category = $this->option('category');

        // Build query
        $query = Entry::query();

        if ($collectionName !== null) {
            /** @var Collection|null $collection */
            $collection = Collection::query()->where('name', $collectionName)->first();
            if ($collection === null) {
                $this->error("Collection '{$collectionName}' not found.");

                return self::FAILURE;
            }
            $query->whereHas('collections', function ($q) use ($collection): void {
                $q->where('collections.id', $collection->id);
            });
        }

        if ($category !== null) {
            $query->where('category', $category);
        }

        $entries = $query->get();

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
                'markdown' => $markdownExporter->export($entry),
                'json' => json_encode($entry->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
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
     */
    private function generateFilename(Entry $entry, string $format): string
    {
        $extension = $format === 'json' ? 'json' : 'md';
        $slug = $this->slugify($entry->title);

        return "{$entry->id}-{$slug}.{$extension}";
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

        if ($text === '') {
            return 'untitled';
        }

        return substr($text, 0, 50);
    }
}
