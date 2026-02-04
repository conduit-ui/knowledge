<?php

declare(strict_types=1);

namespace App\Services;

class MarkdownExporter
{
    /**
     * Export an entry array to markdown format with YAML front matter.
     *
     * @param  array<string, mixed>  $entry
     */
    public function exportArray(array $entry): string
    {
        $frontMatter = $this->buildFrontMatterFromArray($entry);
        $content = $entry['content'] ?? '';

        return "---\n{$frontMatter}---\n\n# {$entry['title']}\n\n{$content}\n";
    }

    /**
     * Build YAML front matter from an entry array.
     *
     * @param  array<string, mixed>  $entry
     */
    private function buildFrontMatterFromArray(array $entry): string
    {
        $yaml = [];
        $yaml[] = "id: {$entry['id']}";
        $yaml[] = "title: \"{$this->escapeYaml($entry['title'])}\"";

        if (! empty($entry['category'])) {
            $yaml[] = "category: \"{$this->escapeYaml($entry['category'])}\"";
        }

        if (! empty($entry['module'])) {
            $yaml[] = "module: \"{$this->escapeYaml($entry['module'])}\"";
        }

        $yaml[] = "priority: \"{$entry['priority']}\"";
        $yaml[] = "confidence: {$entry['confidence']}";
        $yaml[] = "status: \"{$entry['status']}\"";

        if (! empty($entry['tags']) && is_array($entry['tags']) && $entry['tags'] !== []) {
            $yaml[] = 'tags:';
            foreach ($entry['tags'] as $tag) {
                $yaml[] = "  - \"{$this->escapeYaml($tag)}\"";
            }
        }

        $yaml[] = "usage_count: {$entry['usage_count']}";
        $yaml[] = "created_at: \"{$entry['created_at']}\"";
        $yaml[] = "updated_at: \"{$entry['updated_at']}\"";

        return implode("\n", $yaml)."\n";
    }

    /**
     * Escape special characters for YAML.
     */
    private function escapeYaml(string $value): string
    {
        return str_replace('"', '\\"', $value);
    }
}
