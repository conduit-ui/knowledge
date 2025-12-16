<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmbeddingServiceInterface;

class StubEmbeddingService implements EmbeddingServiceInterface
{
    /**
     * Generate an embedding vector for the given text.
     * This is a stub implementation that returns an empty array.
     *
     * @param  string  $text  The text to generate an embedding for
     * @return array<int, float> Empty array (stub implementation)
     */
    public function generate(string $text): array
    {
        return [];
    }

    /**
     * Calculate the cosine similarity between two embedding vectors.
     * This is a stub implementation that returns 0.0.
     *
     * @param  array<int, float>  $a  First embedding vector
     * @param  array<int, float>  $b  Second embedding vector
     * @return float Always returns 0.0 (stub implementation)
     */
    public function similarity(array $a, array $b): float
    {
        return 0.0;
    }
}
