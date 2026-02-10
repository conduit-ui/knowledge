<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;

class CorrectionService
{
    private const CONFLICT_SIMILARITY_THRESHOLD = 0.85;

    private const SUPERSEDED_CONFIDENCE = 10;

    public function __construct(
        private readonly QdrantService $qdrant,
    ) {}

    /**
     * Execute a correction: update the target entry, find and supersede conflicts, create corrected entry, log to daily log.
     *
     * @return array{corrected_entry_id: string, superseded_ids: array<string|int>, conflicts_found: int, log_entry_id: string}
     */
    public function correct(string|int $id, string $newValue): array
    {
        // 1. Fetch the original entry
        $original = $this->qdrant->getById($id);
        if ($original === null) {
            throw new \RuntimeException("Entry not found: {$id}");
        }

        // 2. Search for conflicting entries using the original content
        $conflicts = $this->findConflicts($original, $id);

        // 3. Supersede conflicting entries
        $supersededIds = $this->supersedConflicts($conflicts, $id);

        // 4. Supersede the original entry itself
        $this->qdrant->updateFields($id, [
            'status' => 'deprecated',
            'confidence' => self::SUPERSEDED_CONFIDENCE,
            'tags' => $this->appendTag($original['tags'] ?? [], 'superseded'),
        ]);

        // 5. Create corrected entry
        $correctedId = $this->createCorrectedEntry($original, $newValue);

        // 6. Log correction to daily log
        $logId = $this->logCorrection($original, $newValue, $correctedId, $supersededIds);

        return [
            'corrected_entry_id' => $correctedId,
            'superseded_ids' => $supersededIds,
            'conflicts_found' => count($conflicts),
            'log_entry_id' => $logId,
        ];
    }

    /**
     * Find entries that conflict with the original entry's content.
     *
     * @param  array<string, mixed>  $original
     * @return array<int, array<string, mixed>>
     */
    public function findConflicts(array $original, string|int $excludeId): array
    {
        $searchText = $original['title'].' '.$original['content'];

        $results = $this->qdrant->search($searchText, [], 20);

        return $results->filter(function (array $entry) use ($excludeId): bool {
            // Exclude the original entry itself
            if ((string) $entry['id'] === (string) $excludeId) {
                return false;
            }

            // Only consider entries that are not already deprecated
            if (($entry['status'] ?? '') === 'deprecated') {
                return false;
            }

            // Must meet similarity threshold
            return $entry['score'] >= self::CONFLICT_SIMILARITY_THRESHOLD;
        })->values()->toArray();
    }

    /**
     * Mark conflicting entries as superseded.
     *
     * @param  array<int, array<string, mixed>>  $conflicts
     * @return array<string|int>
     */
    public function supersedConflicts(array $conflicts, string|int $correctedFromId): array
    {
        $supersededIds = [];

        foreach ($conflicts as $conflict) {
            $conflictId = $conflict['id'];
            $existingTags = $conflict['tags'] ?? [];

            $this->qdrant->updateFields($conflictId, [
                'status' => 'deprecated',
                'confidence' => self::SUPERSEDED_CONFIDENCE,
                'tags' => $this->appendTag(
                    is_array($existingTags) ? $existingTags : [],
                    'superseded'
                ),
            ]);

            $supersededIds[] = $conflictId;
        }

        return $supersededIds;
    }

    /**
     * Create a new corrected entry based on the original.
     *
     * @param  array<string, mixed>  $original
     */
    private function createCorrectedEntry(array $original, string $newValue): string
    {
        $correctedId = Str::uuid()->toString();

        $this->qdrant->upsert([
            'id' => $correctedId,
            'title' => $original['title'],
            'content' => $newValue,
            'category' => $original['category'] ?? null,
            'module' => $original['module'] ?? null,
            'priority' => $original['priority'] ?? 'medium',
            'confidence' => 90,
            'status' => 'validated',
            'tags' => $this->appendTag($original['tags'] ?? [], 'corrected'),
            'evidence' => 'user correction',
            'last_verified' => now()->toIso8601String(),
        ], 'default', true);

        return $correctedId;
    }

    /**
     * Log the correction to the daily log.
     *
     * @param  array<string, mixed>  $original
     * @param  array<string|int>  $supersededIds
     */
    private function logCorrection(array $original, string $newValue, string $correctedId, array $supersededIds): string
    {
        $logId = Str::uuid()->toString();
        $today = now()->format('Y-m-d');

        $supersededList = count($supersededIds) > 0
            ? implode(', ', $supersededIds)
            : 'none';

        $content = "**Correction Log - {$today}**\n\n"
            ."- **Original Entry**: {$original['id']}\n"
            ."- **Original Title**: {$original['title']}\n"
            ."- **Corrected Entry**: {$correctedId}\n"
            ."- **New Value**: {$newValue}\n"
            ."- **Superseded Entries**: {$supersededList}\n"
            ."- **Evidence**: user correction\n"
            .'- **Timestamp**: '.now()->toIso8601String();

        $this->qdrant->upsert([
            'id' => $logId,
            'title' => "Correction Log - {$original['title']} - {$today}",
            'content' => $content,
            'category' => $original['category'] ?? null,
            'tags' => ['correction-log', $today, 'correction'],
            'priority' => 'medium',
            'confidence' => 100,
            'status' => 'validated',
            'evidence' => 'user correction',
            'last_verified' => now()->toIso8601String(),
        ], 'default', true);

        return $logId;
    }

    /**
     * Append a tag to an existing tags array without duplicates.
     *
     * @param  array<string>  $tags
     * @return array<string>
     */
    private function appendTag(array $tags, string $tag): array
    {
        if (! in_array($tag, $tags, true)) {
            $tags[] = $tag;
        }

        return array_values($tags);
    }
}
