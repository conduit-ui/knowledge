<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use App\Services\MarkdownExporter;
use LaravelZero\Framework\Commands\Command;

class KnowledgeExportCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'export
                            {id : The ID of the knowledge entry to export}
                            {--format=markdown : Export format (markdown, json)}
                            {--output= : Output file path (default: stdout)}';

    /**
     * @var string
     */
    protected $description = 'Export a single knowledge entry';

    public function handle(MarkdownExporter $markdownExporter): int
    {
        /** @var string|int|null $id */
        $id = $this->argument('id');
        /** @var string $format */
        $format = $this->option('format') ?? 'markdown';
        /** @var string|null $output */
        $output = $this->option('output');

        // Validate ID
        if (! is_numeric($id)) {
            $this->error('The ID must be a valid number.');

            return self::FAILURE;
        }

        /** @var \App\Models\Entry|null $entry */
        $entry = Entry::query()->find((int) $id);

        if ($entry === null) {
            $this->error('Entry not found.');

            return self::FAILURE;
        }

        // Generate export content based on format
        $content = match ($format) {
            'markdown' => $markdownExporter->export($entry),
            'json' => json_encode($entry->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            // @codeCoverageIgnoreStart
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
            // @codeCoverageIgnoreEnd
        };

        // Output to file or stdout
        if ($output !== null && $output !== '') {
            $directory = dirname($output);
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($output, $content);
            $this->info("Exported entry #{$id} to: {$output}");
        } else {
            // @codeCoverageIgnoreStart
            // Defensive check - content is always string from match above
            if (is_string($content)) {
                $this->line($content);
            }
            // @codeCoverageIgnoreEnd
        }

        return self::SUCCESS;
    }
}
