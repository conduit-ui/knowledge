<?php

declare(strict_types=1);

namespace App\Exceptions\Qdrant;

class EmbeddingException extends QdrantException
{
    public static function generationFailed(string $text): self
    {
        $preview = mb_substr($text, 0, 50);

        return new self("Failed to generate embedding for text: {$preview}...");
    }
}
