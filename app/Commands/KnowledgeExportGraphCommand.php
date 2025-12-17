<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\GraphExporter;
use LaravelZero\Framework\Commands\Command;

class KnowledgeExportGraphCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'export:graph
                            {--format=json : Export format (json, cytoscape, dot)}
                            {--output= : Output file path (default: stdout)}';

    /**
     * @var string
     */
    protected $description = 'Export knowledge graph for visualization';

    public function handle(GraphExporter $exporter): int
    {
        /** @var string $format */
        $format = $this->option('format') ?? 'json';
        /** @var string|null $output */
        $output = $this->option('output');

        try {
            // Generate graph data based on format
            $content = match ($format) {
                'json' => json_encode($exporter->exportGraph(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'cytoscape' => json_encode($exporter->exportCytoscapeGraph(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'dot' => $exporter->exportDotGraph(),
                default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
            };

            // Output to file or stdout
            if ($output !== null && $output !== '') {
                $directory = dirname($output);
                if (! is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                file_put_contents($output, $content);
                $this->info("Exported knowledge graph to: {$output}");
            } else {
                // @codeCoverageIgnoreStart
                // Defensive check - content is always string from json_encode
                if (is_string($content)) {
                    $this->line($content);
                }
                // @codeCoverageIgnoreEnd
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to export graph: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
