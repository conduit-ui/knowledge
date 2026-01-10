<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Entry;

class KnowledgeSearchService
{
    public function __construct(
        private readonly SemanticSearchService $semanticSearch
    ) {}

    /**
     * Find similar issues in knowledge base.
     */
    public function findSimilar(string $title, ?string $body = null): array
    {
        // Combine title and body for better matching
        if ($body !== null) {
            $searchText = $title.' '.substr($body, 0, 500);
        } else {
            $searchText = $title;
        }

        // Use semantic search to find related entries
        $entries = $this->semanticSearch->search($searchText, 5);

        $results = [];

        foreach ($entries as $entry) {
            $results[] = [
                'id' => $entry->id,
                'title' => $entry->title,
                'category' => $entry->category,
                'similarity' => $entry->similarity ?? 0,
                'content_preview' => substr($entry->content, 0, 200),
            ];
        }

        return $results;
    }

    /**
     * Create knowledge entry from completed issue.
     */
    public function createFromIssue(array $issue, array $analysis, array $todos, array $prData): Entry
    {
        $content = $this->buildIssueContent($issue, $analysis, $todos, $prData);

        $entry = Entry::create([
            'title' => "Issue #{$issue['number']}: {$issue['title']}",
            'content' => $content,
            'category' => $this->determineCategory($issue),
            'tags' => $this->extractTags($issue, $analysis),
            'priority' => $this->determinePriority($issue),
            'confidence' => $analysis['confidence'],
            'metadata' => [
                'issue_number' => $issue['number'],
                'pr_url' => $prData['url'] ?? null,
                'files_changed' => array_column($analysis['files'], 'path'),
                'complexity' => $analysis['complexity'],
            ],
        ]);

        return $entry;
    }

    /**
     * Build content for knowledge entry.
     */
    private function buildIssueContent(array $issue, array $analysis, array $todos, array $prData): string
    {
        $content = "# Issue Implementation\n\n";
        $content .= "**Original Issue:** {$issue['title']}\n\n";
        $content .= "## Analysis\n\n";
        $content .= "**Approach:** {$analysis['approach']}\n";
        $content .= "**Complexity:** {$analysis['complexity']}\n";
        $content .= "**Confidence:** {$analysis['confidence']}%\n\n";

        $content .= "## Files Modified\n\n";
        foreach ($analysis['files'] as $file) {
            $content .= "- `{$file['path']}` - {$file['change_type']}\n";
        }

        $content .= "\n## Implementation Tasks\n\n";
        foreach ($todos as $index => $todo) {
            $num = $index + 1;
            $content .= "{$num}. {$todo['content']}\n";
        }

        if (isset($prData['url'])) {
            $content .= "\n## Pull Request\n\n";
            $content .= "PR: {$prData['url']}\n";
        }

        return $content;
    }

    /**
     * Determine category from issue.
     */
    private function determineCategory(array $issue): string
    {
        $labels = array_column($issue['labels'] ?? [], 'name');

        foreach ($labels as $label) {
            $normalized = strtolower($label);

            if (str_contains($normalized, 'bug')) {
                return 'bug-fix';
            }
            if (str_contains($normalized, 'feature')) {
                return 'feature';
            }
            if (str_contains($normalized, 'refactor')) {
                return 'refactor';
            }
        }

        return 'feature';
    }

    /**
     * Extract tags from issue and analysis.
     */
    private function extractTags(array $issue, array $analysis): array
    {
        $tags = ['github-issue'];

        // Add label names as tags
        foreach ($issue['labels'] ?? [] as $label) {
            $tags[] = strtolower($label['name']);
        }

        // Add complexity as tag
        $tags[] = $analysis['complexity'];

        return array_unique($tags);
    }

    /**
     * Determine priority from issue.
     */
    private function determinePriority(array $issue): string
    {
        $labels = array_column($issue['labels'] ?? [], 'name');

        foreach ($labels as $label) {
            $normalized = strtolower($label);

            if (str_contains($normalized, 'critical') || str_contains($normalized, 'urgent')) {
                return 'critical';
            }
            if (str_contains($normalized, 'high')) {
                return 'high';
            }
            if (str_contains($normalized, 'low')) {
                return 'low';
            }
        }

        return 'medium';
    }
}
