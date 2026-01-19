<?php

declare(strict_types=1);

namespace App\Exceptions\Qdrant;

class CollectionCreationException extends QdrantException
{
    public static function withReason(string $collectionName, string $reason): self
    {
        return new self("Failed to create collection '{$collectionName}': {$reason}");
    }
}
