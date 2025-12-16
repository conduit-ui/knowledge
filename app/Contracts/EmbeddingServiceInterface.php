<?php

declare(strict_types=1);

namespace App\Contracts;

interface EmbeddingServiceInterface
{
    /**
     * Generate an embedding vector for the given text.
     *
     * @param  string  $text  The text to generate an embedding for
     * @return array<int, float> The embedding vector
     */
    public function generate(string $text): array;

    /**
     * Calculate the cosine similarity between two embedding vectors.
     *
     * @param  array<int, float>  $a  First embedding vector
     * @param  array<int, float>  $b  Second embedding vector
     * @return float Similarity score between 0 and 1
     */
    public function similarity(array $a, array $b): float;
}
