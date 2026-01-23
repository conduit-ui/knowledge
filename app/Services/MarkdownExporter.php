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
        $content = is_string($entry['content'] ?? null) ? $entry['content'] : '';
        $title = is_string($entry['title'] ?? null) ? $entry['title'] : '';

        return "---\n{$frontMatter}---\n\n# {$title}\n\n{$content}\n";
    }

    /**
     * Build YAML front matter from an entry array.
     *
     * @param  array<string, mixed>  $entry
     */
    private function buildFrontMatterFromArray(array $entry): string
    {
        $id = $this->getString($entry, 'id');
        $title = $this->getString($entry, 'title');
        $category = $this->getString($entry, 'category');
        $module = $this->getString($entry, 'module');
        $priority = $this->getString($entry, 'priority');
        $confidence = is_numeric($entry['confidence'] ?? null) ? $entry['confidence'] : 0;
        $status = $this->getString($entry, 'status');
        $usageCount = is_numeric($entry['usage_count'] ?? null) ? $entry['usage_count'] : 0;
        $createdAt = $this->getString($entry, 'created_at');
        $updatedAt = $this->getString($entry, 'updated_at');

        $yaml = [];
        $yaml[] = "id: {$id}";
        $yaml[] = "title: \"{$this->escapeYaml($title)}\"";

        if ($category !== '') {
            $yaml[] = "category: \"{$this->escapeYaml($category)}\"";
        }

        if ($module !== '') {
            $yaml[] = "module: \"{$this->escapeYaml($module)}\"";
        }

        $yaml[] = "priority: \"{$priority}\"";
        $yaml[] = "confidence: {$confidence}";
        $yaml[] = "status: \"{$status}\"";

        if (! empty($entry['tags']) && is_array($entry['tags']) && count($entry['tags']) > 0) {
            $yaml[] = 'tags:';
            foreach ($entry['tags'] as $tag) {
                if (is_string($tag)) {
                    $yaml[] = "  - \"{$this->escapeYaml($tag)}\"";
                }
            }
        }

        $yaml[] = "usage_count: {$usageCount}";
        $yaml[] = "created_at: \"{$createdAt}\"";
        $yaml[] = "updated_at: \"{$updatedAt}\"";

        return implode("\n", $yaml)."\n";
    }

    /**
     * Safely get a string value from an array.
     *
     * @param  array<string, mixed>  $entry
     */
    private function getString(array $entry, string $key): string
    {
        $value = $entry[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * Escape special characters for YAML.
     */
    private function escapeYaml(string $value): string
    {
        return str_replace('"', '\\"', $value);
    }
}
