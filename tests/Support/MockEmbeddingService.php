<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\EmbeddingServiceInterface;

class MockEmbeddingService implements EmbeddingServiceInterface
{
    /**
     * Generate a simple mock embedding vector based on text.
     * This creates a deterministic embedding for testing purposes.
     *
     * @param  string  $text  The text to generate an embedding for
     * @return array<int, float> Mock embedding vector
     */
    public function generate(string $text): array
    {
        // Generate a simple deterministic embedding based on the text
        // Use string length and character codes to create variation
        $hash = md5($text);
        $embedding = [];

        for ($i = 0; $i < 10; $i++) {
            $embedding[] = hexdec(substr($hash, $i * 2, 2)) / 255.0;
        }

        return $embedding;
    }

    /**
     * Calculate cosine similarity between two embedding vectors.
     *
     * @param  array<int, float>  $a  First embedding vector
     * @param  array<int, float>  $b  Second embedding vector
     * @return float Similarity score between 0 and 1
     */
    public function similarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || $a === []) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;
        $counter = count($a);

        for ($i = 0; $i < $counter; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $magnitudeA += $a[$i] * $a[$i];
            $magnitudeB += $b[$i] * $b[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
}
