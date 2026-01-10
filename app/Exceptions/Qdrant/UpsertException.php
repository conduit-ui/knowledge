<?php

declare(strict_types=1);

namespace App\Exceptions\Qdrant;

class UpsertException extends QdrantException
{
    public static function withReason(string $reason): self
    {
        return new self("Failed to upsert entry to Qdrant: {$reason}");
    }
}
