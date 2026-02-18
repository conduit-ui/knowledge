<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\KnowledgeException;
use Carbon\Carbon;

class SupersedeService
{
    public function __construct(
        private QdrantService $qdrant,
        private AiService $ai
    ) {}

    /**
     * Mark an entry as superseded by another.
     *
     * @param string|int $oldId
     * @param string|int $newId
     * @param string|null $reason
     * @param string $project
     * @return array{old: array, new: array, reason: string}
     * @throws KnowledgeException
     */
    public function supersede(string|int $oldId, string|int $newId, ?string $reason = null, string $project = 'default'): array
    {
        $oldEntry = $this->qdrant->getById($oldId, $project);
        $newEntry = $this->qdrant->getById($newId, $project);

        if (!$oldEntry || !$newEntry) {
            throw new KnowledgeException('One or both entries not found.');
        }

        if ((string) $oldId === (string) $newId) {
            throw new KnowledgeException('Old and new IDs must be different.');
        }

        $this->checkCircularDependency($oldId, $newId, $project);

        $reason ??= $this->generateReason($oldEntry, $newEntry);

        $updateFields = [
            'status' => 'deprecated',
            'superseded_by' => (string) $newId,
            'superseded_date' => Carbon::now()->toIso8601String(),
            'superseded_reason' => $reason,
            'confidence' => 0, // Deprioritize in search
        ];

        $this->qdrant->updateFields($oldId, $updateFields, $project);

        return [
            'old' => $oldEntry,
            'new' => $newEntry,
            'reason' => $reason,
        ];
    }

    /**
     * Check for circular dependency (max depth 5).
     */
    private function checkCircularDependency(string|int $oldId, string|int $newId, string $project): void
    {
        $chain = [];
        $current = $oldId;

        for ($depth = 0; $depth < 5; $depth++) {
            $entry = $this->qdrant->getById($current, $project);
            if (!$entry || !isset($entry['superseded_by'])) {
                break;
            }

            $current = $entry['superseded_by'];

            if (in_array($current, $chain, true)) {
                throw new KnowledgeException('Circular dependency detected.');
            }

            $chain[] = $current;

            if ($current === $newId) {
                throw new KnowledgeException('Circular supersession detected.');
            }
        }
    }

    /**
     * Generate supersession reason using Grok 4 Fast.
     */
    private function generateReason(array $oldEntry, array $newEntry): string
    {
        $oldExcerpt = substr($oldEntry['content'] ?? '', 0, 200);
        $newExcerpt = substr($newEntry['content'] ?? '', 0, 200);

        $prompt = <<<EOT
You are maintaining a technical knowledge base.

Old Entry: "{$oldEntry['title']}"
Excerpt: "{$oldExcerpt}"

New Entry: "{$newEntry['title']}"
Excerpt: "{$newExcerpt}"

Explain why the new entry supersedes the old one in **one concise sentence**.
Focus on improvements, accuracy, or completeness.

EOT;

        $reason = $this->ai->generate($prompt);

        return trim($reason ?: 'Superseded by more accurate/current information.');
    }
}
