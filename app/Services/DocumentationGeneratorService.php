<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DocumentationGeneratorService
{
    private const CONTEXT_TOKEN_LIMIT = 5000;

    private const DOC_TYPES = [
        'api' => ['api', 'endpoint', 'route', 'service'],
        'architecture' => ['architecture', 'design', 'pattern', 'structure'],
        'debugging' => ['debugging', 'fix', 'error', 'issue', 'problem'],
        'setup' => ['setup', 'install', 'config', 'environment'],
        'feature' => ['feature', 'implementation', 'logic'],
    ];

    public function __construct(
        private readonly TieredSearchService $tieredSearchService,
        private readonly QdrantService $qdrantService,
        private readonly GitContextService $gitContextService,
        private readonly ProjectDetectorService $projectDetectorService,
        private readonly EnhancementQueueService $enhancementQueueService,
    ) {}

    /**
     * Generate comprehensive documentation for the current project.
     */
    public function generateProjectDocumentation(
        array $options = [],
        ?string $project = null
    ): array {
        $project ??= $this->projectDetectorService->detect();

        $context = $this->gatherProjectContext($project, $options);

        $documentation = [
            'project' => $project,
            'generated_at' => now()->toIso8601String(),
            'context_summary' => $this->summarizeContext($context),
            'sections' => $this->generateDocumentationSections($context, $options),
            'metadata' => $this->generateMetadata($project),
        ];

        // Queue for AI enhancement if enabled
        if ($options['enhance'] ?? true) {
            $this->queueForEnhancement($documentation);
        }

        return $documentation;
    }

    /**
     * Generate documentation for a specific component or topic.
     */
    public function generateTopicDocumentation(
        string $topic,
        array $options = [],
        ?string $project = null
    ): array {
        $project ??= $this->projectDetectorService->detect();

        $context = $this->gatherTopicContext($topic, $project, $options);

        return [
            'topic' => $topic,
            'project' => $project,
            'generated_at' => now()->toIso8601String(),
            'context' => $context,
            'documentation' => $this->formatTopicDocumentation($context),
            'related_entries' => $this->findRelatedEntries($topic, $project),
        ];
    }

    /**
     * Generate documentation in markdown format.
     */
    public function generateMarkdownDocumentation(array $documentation): string
    {
        $markdown = "# {$documentation['project']} Documentation\n\n";
        $markdown .= "> Generated on {$documentation['generated_at']}\n\n";

        if (isset($documentation['context_summary'])) {
            $markdown .= "## Context Summary\n\n";
            $markdown .= $documentation['context_summary']."\n\n";
        }

        foreach ($documentation['sections'] as $section => $content) {
            $markdown .= '## '.Str::title($section)."\n\n";
            $markdown .= $content."\n\n";
        }

        if (isset($documentation['metadata'])) {
            $markdown .= "---\n\n";
            $markdown .= "## Metadata\n\n";
            foreach ($documentation['metadata'] as $key => $value) {
                $markdown .= "- **{$key}**: {$value}\n";
            }
            $markdown .= "\n";
        }

        return $markdown;
    }

    /**
     * Gather comprehensive project context.
     */
    private function gatherProjectContext(string $project, array $options): Collection
    {
        $context = collect();

        // Get all entries for documentation generation
        $allEntries = $this->qdrantService->scroll([], $options['limit'] ?? 100, $project);
        $context = $allEntries;

        // Deduplicate and rank by tiered score
        return $context
            ->groupBy('id')
            ->map(fn ($group) => $group->sortByDesc('tiered_score')->first())
            ->filter()
            ->sortByDesc('tiered_score')
            ->take($options['limit'] ?? 100)
            ->values();
    }

    /**
     * Gather context for a specific topic.
     */
    private function gatherTopicContext(string $topic, string $project, array $options): Collection
    {
        $query = $topic.' implementation details and examples';

        return $this->tieredSearchService->search(
            $query,
            [],
            $options['limit'] ?? 50,
            null,
            $project
        );
    }

    /**
     * Generate documentation sections from context.
     */
    private function generateDocumentationSections(Collection $context, array $options): array
    {
        $sections = [];

        // Group by categories and generate sections
        $byCategory = $context->groupBy('category');

        foreach ($byCategory as $category => $entries) {
            if (empty($category)) {
                continue;
            }

            $docType = $this->mapCategoryToDocType($category);
            $sections[$docType] = $this->generateCategoryDocumentation($entries, $category);
        }

        // Generate specific sections
        if ($options['api_docs'] ?? true) {
            $sections['api_documentation'] = $this->generateApiDocumentation($context);
        }

        if ($options['architecture_docs'] ?? true) {
            $sections['architecture_overview'] = $this->generateArchitectureDocumentation($context);
        }

        if ($options['debugging_guide'] ?? true) {
            $sections['debugging_guide'] = $this->generateDebuggingDocumentation($context);
        }

        if ($options['setup_guide'] ?? true) {
            $sections['setup_guide'] = $this->generateSetupDocumentation($context);
        }

        return array_filter($sections);
    }

    /**
     * Generate documentation for a specific category.
     */
    private function generateCategoryDocumentation(Collection $entries, string $category): string
    {
        $markdown = '### '.Str::title($category)."\n\n";

        foreach ($entries as $entry) {
            $markdown .= "#### {$entry['title']}\n\n";
            $markdown .= $entry['content']."\n\n";

            if (! empty($entry['tags'])) {
                $markdown .= '**Tags**: '.implode(', ', $entry['tags'])."\n\n";
            }

            if ($entry['confidence'] > 0) {
                $markdown .= "**Confidence**: {$entry['confidence']}%\n\n";
            }

            $markdown .= "---\n\n";
        }

        return $markdown;
    }

    /**
     * Generate API documentation from context.
     */
    private function generateApiDocumentation(Collection $context): string
    {
        $apiEntries = $context->filter(fn ($entry) => $this->isApiEntry($entry)
        );

        if ($apiEntries->isEmpty()) {
            return "No API-specific documentation found.\n";
        }

        $markdown = "### API Endpoints and Services\n\n";

        foreach ($apiEntries as $entry) {
            $markdown .= "#### {$entry['title']}\n\n";
            $markdown .= $entry['content']."\n\n";

            if (! empty($entry['module'])) {
                $markdown .= "**Module**: {$entry['module']}\n\n";
            }

            $markdown .= "---\n\n";
        }

        return $markdown;
    }

    /**
     * Generate architecture documentation.
     */
    private function generateArchitectureDocumentation(Collection $context): string
    {
        $archEntries = $context->filter(fn ($entry) => in_array($entry['category'], ['architecture', 'design', 'pattern'])
        );

        if ($archEntries->isEmpty()) {
            return "No architecture documentation found.\n";
        }

        $markdown = "### System Architecture\n\n";

        // Group by module if available
        $byModule = $archEntries->groupBy('module');

        foreach ($byModule as $module => $entries) {
            if (! empty($module)) {
                $markdown .= '#### '.Str::title($module)."\n\n";
            }

            foreach ($entries as $entry) {
                $markdown .= "##### {$entry['title']}\n\n";
                $markdown .= $entry['content']."\n\n";
            }
        }

        return $markdown;
    }

    /**
     * Generate debugging documentation.
     */
    private function generateDebuggingDocumentation(Collection $context): string
    {
        $debugEntries = $context->filter(fn ($entry) => $this->isDebuggingEntry($entry)
        );

        if ($debugEntries->isEmpty()) {
            return "No debugging documentation found.\n";
        }

        $markdown = "### Common Issues and Solutions\n\n";

        foreach ($debugEntries as $entry) {
            $markdown .= "#### {$entry['title']}\n\n";
            $markdown .= $entry['content']."\n\n";

            if ($entry['usage_count'] > 0) {
                $markdown .= "**Referenced**: {$entry['usage_count']} times\n\n";
            }

            $markdown .= "---\n\n";
        }

        return $markdown;
    }

    /**
     * Generate setup documentation.
     */
    private function generateSetupDocumentation(Collection $context): string
    {
        $setupEntries = $context->filter(fn ($entry) => $this->isSetupEntry($entry)
        );

        if ($setupEntries->isEmpty()) {
            return "No setup documentation found.\n";
        }

        $markdown = "### Setup and Configuration\n\n";

        foreach ($setupEntries as $entry) {
            $markdown .= "#### {$entry['title']}\n\n";
            $markdown .= $entry['content']."\n\n";
            $markdown .= "---\n\n";
        }

        return $markdown;
    }

    /**
     * Check if entry is API-related.
     */
    private function isApiEntry(array $entry): bool
    {
        $text = strtolower($entry['title'].' '.$entry['content']);

        return str_contains($text, 'api') ||
               str_contains($text, 'endpoint') ||
               str_contains($text, 'route') ||
               str_contains($text, 'service');
    }

    /**
     * Check if entry is debugging-related.
     */
    private function isDebuggingEntry(array $entry): bool
    {
        $text = strtolower($entry['title'].' '.$entry['content']);

        return str_contains($text, 'debug') ||
               str_contains($text, 'error') ||
               str_contains($text, 'fix') ||
               str_contains($text, 'issue') ||
               str_contains($text, 'problem');
    }

    /**
     * Check if entry is setup-related.
     */
    private function isSetupEntry(array $entry): bool
    {
        $text = strtolower($entry['title'].' '.$entry['content']);

        return str_contains($text, 'setup') ||
               str_contains($text, 'install') ||
               str_contains($text, 'config') ||
               str_contains($text, 'environment');
    }

    /**
     * Map category to documentation type.
     */
    private function mapCategoryToDocType(string $category): string
    {
        foreach (self::DOC_TYPES as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains(strtolower($category), $keyword)) {
                    return $type;
                }
            }
        }

        return 'general';
    }

    /**
     * Generate metadata for documentation.
     */
    private function generateMetadata(string $project): array
    {
        $gitContext = $this->gitContextService->getContext();

        return [
            'project' => $project,
            'repository' => $gitContext['repository'] ?? 'Unknown',
            'branch' => $gitContext['branch'] ?? 'Unknown',
            'commit' => $gitContext['commit'] ?? 'Unknown',
            'author' => $gitContext['author'] ?? 'Unknown',
            'entries_count' => $this->qdrantService->count($project),
            'generator' => 'Knowledge CLI Documentation Generator',
        ];
    }

    /**
     * Summarize the gathered context.
     */
    private function summarizeContext(Collection $context): string
    {
        if ($context->isEmpty()) {
            return 'No context available for documentation generation.';
        }

        $categories = $context->pluck('category')->filter()->unique();
        $totalEntries = $context->count();
        $avgConfidence = $context->avg('confidence');

        $summary = "Generated from {$totalEntries} knowledge entries";

        if ($categories->count() > 0) {
            $summary .= ' covering '.implode(', ', $categories->toArray());
        }

        if ($avgConfidence > 0) {
            $summary .= ' with an average confidence of '.round($avgConfidence, 1).'%';
        }

        $summary .= '.';

        return $summary;
    }

    /**
     * Format topic-specific documentation.
     */
    private function formatTopicDocumentation(Collection $context): string
    {
        $markdown = "# Topic Documentation\n\n";

        foreach ($context as $entry) {
            $markdown .= "## {$entry['title']}\n\n";
            $markdown .= $entry['content']."\n\n";

            if (! empty($entry['tags'])) {
                $markdown .= '**Tags**: '.implode(', ', $entry['tags'])."\n\n";
            }

            $markdown .= "---\n\n";
        }

        return $markdown;
    }

    /**
     * Find related entries for a topic.
     */
    private function findRelatedEntries(string $topic, string $project): Collection
    {
        return $this->tieredSearchService->search(
            $topic.' related information and examples',
            [],
            10,
            null,
            $project
        );
    }

    /**
     * Queue documentation for AI enhancement.
     */
    private function queueForEnhancement(array $documentation): void
    {
        $enhancementData = [
            'type' => 'documentation_enhancement',
            'data' => $documentation,
            'queued_at' => now()->toIso8601String(),
        ];

        $this->enhancementQueueService->queue($enhancementData);
    }
}
