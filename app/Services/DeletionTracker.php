<?php

declare(strict_types=1);

namespace App\Services;

class DeletionTracker
{
    private string $filePath;

    /** @var array<string, string> Map of unique_id => deleted_at ISO timestamp */
    private array $deletions = [];

    private bool $loaded = false;

    public function __construct(
        private readonly KnowledgePathService $pathService,
    ) {
        $this->filePath = $this->pathService->getKnowledgeDirectory().'/deletions.json';
    }

    /**
     * Track a deleted entry by its unique ID.
     */
    public function track(string $uniqueId, ?string $deletedAt = null): void
    {
        $this->load();
        $this->deletions[$uniqueId] = $deletedAt ?? now()->toIso8601String();
        $this->save();
    }

    /**
     * Track multiple deleted entries.
     *
     * @param  array<string>  $uniqueIds
     */
    public function trackMany(array $uniqueIds, ?string $deletedAt = null): void
    {
        $this->load();
        $timestamp = $deletedAt ?? now()->toIso8601String();

        foreach ($uniqueIds as $uniqueId) {
            $this->deletions[$uniqueId] = $timestamp;
        }

        $this->save();
    }

    /**
     * Get all tracked deletions.
     *
     * @return array<string, string> Map of unique_id => deleted_at
     */
    public function all(): array
    {
        $this->load();

        return $this->deletions;
    }

    /**
     * Get all unique IDs that have been marked for deletion.
     *
     * @return array<string>
     */
    public function getDeletedIds(): array
    {
        $this->load();

        return array_keys($this->deletions);
    }

    /**
     * Remove a tracked deletion (after successful cloud deletion).
     */
    public function remove(string $uniqueId): void
    {
        $this->load();
        unset($this->deletions[$uniqueId]);
        $this->save();
    }

    /**
     * Remove multiple tracked deletions.
     *
     * @param  array<string>  $uniqueIds
     */
    public function removeMany(array $uniqueIds): void
    {
        $this->load();

        foreach ($uniqueIds as $uniqueId) {
            unset($this->deletions[$uniqueId]);
        }

        $this->save();
    }

    /**
     * Clear all tracked deletions.
     */
    public function clear(): void
    {
        $this->deletions = [];
        $this->save();
    }

    /**
     * Check if an entry is tracked for deletion.
     */
    public function isTracked(string $uniqueId): bool
    {
        $this->load();

        return isset($this->deletions[$uniqueId]);
    }

    /**
     * Get the count of tracked deletions.
     */
    public function count(): int
    {
        $this->load();

        return count($this->deletions);
    }

    /**
     * Get the file path (for testing).
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Load deletions from disk.
     */
    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        if (file_exists($this->filePath)) {
            $content = file_get_contents($this->filePath);

            if ($content !== false) {
                $data = json_decode($content, true);

                if (is_array($data)) {
                    $this->deletions = $data;
                }
            }
        }

        $this->loaded = true;
    }

    /**
     * Save deletions to disk.
     */
    private function save(): void
    {
        $dir = dirname($this->filePath);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->filePath,
            json_encode($this->deletions, JSON_PRETTY_PRINT)
        );
    }
}
