<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Entry;

class MarkdownExporter
{
    /**
     * Export an entry to markdown format with YAML front matter.
     */
    public function export(Entry $entry): string
    {
        $frontMatter = $this->buildFrontMatter($entry);
        $content = $entry->content;

        return "---\n{$frontMatter}---\n\n# {$entry->title}\n\n{$content}\n";
    }

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
     * Build YAML front matter for an entry.
     */
    private function buildFrontMatter(Entry $entry): string
    {
        $yaml = [];
        $yaml[] = "id: {$entry->id}";
        $yaml[] = "title: \"{$this->escapeYaml($entry->title)}\"";

        if ($entry->category) {
            $yaml[] = "category: \"{$this->escapeYaml($entry->category)}\"";
        }

        if ($entry->module) {
            $yaml[] = "module: \"{$this->escapeYaml($entry->module)}\"";
        }

        $yaml[] = "priority: \"{$entry->priority}\"";
        $yaml[] = "confidence: {$entry->confidence}";
        $yaml[] = "status: \"{$entry->status}\"";

        if ($entry->tags && count($entry->tags) > 0) {
            $yaml[] = 'tags:';
            foreach ($entry->tags as $tag) {
                $yaml[] = "  - \"{$this->escapeYaml($tag)}\"";
            }
        }

        if ($entry->source) {
            $yaml[] = "source: \"{$this->escapeYaml($entry->source)}\"";
        }

        if ($entry->ticket) {
            $yaml[] = "ticket: \"{$this->escapeYaml($entry->ticket)}\"";
        }

        if ($entry->author) {
            $yaml[] = "author: \"{$this->escapeYaml($entry->author)}\"";
        }

        if ($entry->files && count($entry->files) > 0) {
            $yaml[] = 'files:';
            foreach ($entry->files as $file) {
                $yaml[] = "  - \"{$this->escapeYaml($file)}\"";
            }
        }

        if ($entry->repo) {
            $yaml[] = "repo: \"{$this->escapeYaml($entry->repo)}\"";
        }

        if ($entry->branch) {
            $yaml[] = "branch: \"{$this->escapeYaml($entry->branch)}\"";
        }

        if ($entry->commit) {
            $yaml[] = "commit: \"{$this->escapeYaml($entry->commit)}\"";
        }

        $yaml[] = "usage_count: {$entry->usage_count}";

        if ($entry->last_used) {
            $yaml[] = "last_used: \"{$entry->last_used->toIso8601String()}\"";
        }

        if ($entry->validation_date) {
            $yaml[] = "validation_date: \"{$entry->validation_date->toIso8601String()}\"";
        }

        $yaml[] = "created_at: \"{$entry->created_at->toIso8601String()}\"";
        $yaml[] = "updated_at: \"{$entry->updated_at->toIso8601String()}\"";

        return implode("\n", $yaml)."\n";
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

        if (! empty($entry['tags']) && is_array($entry['tags']) && count($entry['tags']) > 0) {
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
