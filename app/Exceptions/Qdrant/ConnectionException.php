<?php

declare(strict_types=1);

namespace App\Exceptions\Qdrant;

class ConnectionException extends QdrantException
{
    public static function withMessage(string $message): self
    {
        return new self("Qdrant connection failed: {$message}");
    }
}
