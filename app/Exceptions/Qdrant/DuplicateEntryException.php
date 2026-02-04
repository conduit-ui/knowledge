<?php

declare(strict_types=1);

namespace App\Exceptions\Qdrant;

class DuplicateEntryException extends QdrantException
{
    public const TYPE_HASH = 'hash';

    public const TYPE_SIMILARITY = 'similarity';

    public function __construct(
        string $message,
        public readonly string $duplicateType,
        public readonly string|int $existingId,
        public readonly ?float $similarityScore = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Create exception for hash-based duplicate detection.
     */
    public static function hashMatch(string|int $existingId, string $contentHash): self
    {
        return new self(
            "Duplicate entry detected: content hash '{$contentHash}' already exists with ID '{$existingId}'",
            self::TYPE_HASH,
            $existingId
        );
    }

    /**
     * Create exception for vector similarity-based duplicate detection.
     */
    public static function similarityMatch(string|int $existingId, float $similarityScore): self
    {
        $percentage = round($similarityScore * 100, 1);

        return new self(
            "Potential duplicate detected: entry has {$percentage}% similarity with existing entry ID '{$existingId}'",
            self::TYPE_SIMILARITY,
            $existingId,
            $similarityScore
        );
    }
}
