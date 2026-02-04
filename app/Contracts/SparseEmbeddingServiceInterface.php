<?php

declare(strict_types=1);

namespace App\Contracts;

interface SparseEmbeddingServiceInterface
{
    /**
     * Generate a sparse vector representation for the given text.
     *
     * Returns an associative array with 'indices' and 'values' keys,
     * suitable for Qdrant sparse vector format.
     *
     * @param  string  $text  The text to generate sparse embedding for
     * @return array{indices: array<int>, values: array<float>}
     */
    public function generate(string $text): array;
}
