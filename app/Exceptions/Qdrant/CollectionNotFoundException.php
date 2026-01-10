<?php

declare(strict_types=1);

namespace App\Exceptions\Qdrant;

class CollectionNotFoundException extends QdrantException
{
    public static function forCollection(string $collectionName): self
    {
        return new self("Collection '{$collectionName}' not found in Qdrant");
    }
}
