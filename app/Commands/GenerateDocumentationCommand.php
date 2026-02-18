<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\ResolvesProject;
use App\Services\DocumentationGeneratorService;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

use LaravelZero\Framework\Commands\Command;

class GenerateDocumentationCommand extends Command
{
    use ResolvesProject;

    protected $signature = 'generate:docs
                            {--topic= : Generate docs for specific topic}
                            {--format=markdown : Output format (markdown, json)}
                            {--output= : Output file path}
                            {--include-drafts : Include draft entries in documentation}
                            {--limit=100 : Maximum number of entries to include}
                            {--no-enhance : Skip AI enhancement}
                            {--api-docs : Include API documentation}
                            {--architecture-docs : Include architecture documentation}
                            {--debugging-guide : Include debugging guide}
                            {--setup-guide : Include setup guide}
                            {--project= : Override project namespace}
                            {--global : Generate docs across all projects}';

    protected $description = 'Generate comprehensive documentation from knowledge base';

    public function handle(DocumentationGeneratorService $docGenerator): int
    {
        $project = $this->resolveProject();

        // Build options array
        $options = [
            'include_drafts' => $this->option('include-drafts'),
            'limit' => (int) $this->option('limit'),
            'enhance' => ! $this->option('no-enhance'),
            'api_docs' => $this->option('api-docs'),
            'architecture_docs' => $this->option('architecture-docs'),
            'debugging_guide' => $this->option('debugging-guide'),
            'setup_guide' => $this->option('setup-guide'),
        ];

        // If no specific doc types selected, enable all
        $hasDocTypes = $options['api_docs'] || $options['architecture_docs'] ||
                      $options['debugging_guide'] || $options['setup_guide'];

        if (! $hasDocTypes) {
            $selected = multiselect(
                'Select documentation types to generate:',
                [
                    'api_docs' => 'API Documentation',
                    'architecture_docs' => 'Architecture Overview',
                    'debugging_guide' => 'Debugging Guide',
                    'setup_guide' => 'Setup Guide',
                ],
                default: ['api_docs', 'architecture_docs', 'debugging_guide', 'setup_guide']
            );

            foreach ($selected as $type) {
                $options[$type] = true;
            }
        }

        try {
            if ($topic = $this->option('topic')) {
                $documentation = spin(
                    fn () => $docGenerator->generateTopicDocumentation($topic, $options, $project),
                    'Generating topic documentation...'
                );
            } else {
                $documentation = spin(
                    fn () => $docGenerator->generateProjectDocumentation($options, $project),
                    'Generating project documentation...'
                );
            }

            // Format output
            $format = $this->option('format');
            $output = $this->formatOutput($documentation, $format);

            // Handle output
            if ($outputPath = $this->option('output')) {
                $this->ensureDirectoryExists(dirname($outputPath));
                File::put($outputPath, $output);
                outro("Documentation generated and saved to: {$outputPath}");
            } else {
                $this->displayOutput($output, $format);
            }

            // Display summary
            $this->displaySummary($documentation);

            return self::SUCCESS;

        } catch (\Exception $e) {
            error('Failed to generate documentation: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Format documentation output.
     */
    private function formatOutput(array $documentation, string $format): string
    {
        return match ($format) {
            'json' => json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'markdown' => $this->option('topic')
                ? $documentation['documentation'] ?? ''
                : $this->formatProjectMarkdown($documentation),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };
    }

    /**
     * Format project documentation as markdown.
     */
    private function formatProjectMarkdown(array $documentation): string
    {
        $markdown = "# {$documentation['project']} Documentation\n\n";
        $markdown .= "> Generated on {$documentation['generated_at']}\n\n";

        if (isset($documentation['context_summary'])) {
            $markdown .= "## Context Summary\n\n";
            $markdown .= $documentation['context_summary']."\n\n";
        }

        foreach ($documentation['sections'] as $section => $content) {
            $markdown .= '## '.ucwords(str_replace('_', ' ', $section))."\n\n";
            $markdown .= $content."\n\n";
        }

        if (isset($documentation['metadata'])) {
            $markdown .= "---\n\n";
            $markdown .= "## Metadata\n\n";
            foreach ($documentation['metadata'] as $key => $value) {
                $markdown .= '- **'.ucwords(str_replace('_', ' ', $key))."**: {$value}\n";
            }
            $markdown .= "\n";
        }

        return $markdown;
    }

    /**
     * Display output to console.
     */
    private function displayOutput(string $output, string $format): void
    {
        if ($format === 'json') {
            info('Generated Documentation (JSON):');
            $this->line($output);
        } else {
            // For markdown, show a preview and ask to display full content
            $lines = explode("\n", $output);
            $preview = implode("\n", array_slice($lines, 0, 50));

            info('Generated Documentation (Markdown - Preview):');
            $this->line($preview);

            if (count($lines) > 50) {
                $showFull = text(
                    'Show full documentation? (y/N)',
                    default: 'n',
                    required: false
                );

                if (strtolower($showFull) === 'y' || strtolower($showFull) === 'yes') {
                    $this->line($output);
                }
            } else {
                $this->line($output);
            }
        }
    }

    /**
     * Display documentation summary.
     */
    private function displaySummary(array $documentation): void
    {
        if ($this->option('topic')) {
            $contextCount = count($documentation['context'] ?? []);
            $relatedCount = count($documentation['related_entries'] ?? []);

            table(
                ['Metric', 'Count'],
                [
                    ['Context Entries', $contextCount],
                    ['Related Entries', $relatedCount],
                ]
            );
        } else {
            $sectionsCount = count($documentation['sections'] ?? []);
            $metadata = $documentation['metadata'] ?? [];

            table(
                ['Metric', 'Value'],
                [
                    ['Documentation Sections', $sectionsCount],
                    ['Knowledge Entries', $metadata['entries_count'] ?? 'Unknown'],
                    ['Project', $documentation['project'] ?? 'Unknown'],
                    ['Repository', $metadata['repository'] ?? 'Unknown'],
                ]
            );
        }

        if (isset($documentation['metadata'])) {
            $gitInfo = $documentation['metadata'];
            if (isset($gitInfo['branch']) && isset($gitInfo['commit'])) {
                warning("Generated from branch {$gitInfo['branch']} at commit {$gitInfo['commit']}");
            }
        }
    }

    /**
     * Ensure output directory exists.
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
