<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SparseEmbeddingServiceInterface;

/**
 * BM25-inspired sparse embedding service.
 *
 * Generates sparse vectors using term frequency and token hashing.
 * This provides a simple, fast sparse representation suitable for
 * hybrid search with RRF fusion in Qdrant.
 */
class Bm25SparseEmbeddingService implements SparseEmbeddingServiceInterface
{
    /**
     * Common English stop words to filter out.
     *
     * @var array<string>
     */
    private const STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
        'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'or', 'that',
        'the', 'to', 'was', 'were', 'will', 'with', 'this', 'they', 'but',
        'have', 'had', 'what', 'when', 'where', 'who', 'which', 'why', 'how',
        'all', 'each', 'every', 'both', 'few', 'more', 'most', 'other', 'some',
        'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too',
        'very', 'can', 'just', 'should', 'now', 'your', 'you', 'i', 'we', 'our',
        'my', 'me', 'him', 'her', 'their', 'them', 'his', 'she', 'us', 'been',
        'being', 'do', 'does', 'did', 'doing', 'would', 'could', 'may', 'might',
        'must', 'shall', 'about', 'above', 'after', 'again', 'before', 'below',
        'between', 'into', 'through', 'during', 'out', 'over', 'under', 'up',
        'down', 'off', 'then', 'once', 'here', 'there', 'any', 'also',
    ];

    public function __construct(
        private readonly int $vocabSize = 30000,
        private readonly float $k1 = 1.2,
        private readonly float $b = 0.75,
        private readonly float $avgDocLength = 100.0,
    ) {}

    /**
     * Generate sparse vector using BM25-inspired term weighting.
     *
     * @return array{indices: array<int>, values: array<float>}
     */
    public function generate(string $text): array
    {
        if (trim($text) === '') {
            return ['indices' => [], 'values' => []];
        }

        $tokens = $this->tokenize($text);

        if ($tokens === []) {
            return ['indices' => [], 'values' => []];
        }

        // Count term frequencies
        $termFrequencies = array_count_values($tokens);
        $docLength = count($tokens);

        $indices = [];
        $values = [];

        foreach ($termFrequencies as $term => $tf) {
            // Hash term to vocabulary index
            $index = $this->hashTerm((string) $term);

            // Calculate BM25-like score (simplified without IDF since we don't have corpus stats)
            $numerator = $tf * ($this->k1 + 1);
            $denominator = $tf + $this->k1 * (1 - $this->b + $this->b * ($docLength / $this->avgDocLength));
            $score = $numerator / $denominator;

            $indices[] = $index;
            $values[] = $score;
        }

        // Sort by index for consistent ordering (Qdrant expects sorted indices)
        array_multisort($indices, SORT_ASC, $values);

        return [
            'indices' => $indices,
            'values' => $values,
        ];
    }

    /**
     * Tokenize text into normalized terms.
     *
     * @return array<string>
     */
    private function tokenize(string $text): array
    {
        // Normalize to lowercase
        $text = mb_strtolower($text);

        // Remove special characters, keep alphanumeric and spaces
        $text = (string) preg_replace('/[^a-z0-9\s]/', ' ', $text);

        // Split into tokens
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($tokens === false) {
            return [];
        }

        // Filter stop words and short tokens
        return array_values(array_filter(
            $tokens,
            fn (string $token): bool => strlen($token) > 2 && ! in_array($token, self::STOP_WORDS, true)
        ));
    }

    /**
     * Hash term to vocabulary index using consistent hashing.
     */
    private function hashTerm(string $term): int
    {
        // Use xxh128 for fast, consistent hashing
        $hash = hash('xxh128', $term);

        // Convert first 8 bytes to integer and mod by vocab size
        return abs((int) hexdec(substr($hash, 0, 15))) % $this->vocabSize;
    }
}
