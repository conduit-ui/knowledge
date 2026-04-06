<?php

declare(strict_types=1);

namespace Tests\Support;

use TheShit\Vector\Contracts\EmbeddingClient;

class MockEmbeddingService implements EmbeddingClient
{
    /**
     * @return array<float>
     */
    public function embed(string $text): array
    {
        $hash = md5($text);
        $embedding = [];

        for ($i = 0; $i < 10; $i++) {
            $embedding[] = hexdec(substr($hash, $i * 2, 2)) / 255.0;
        }

        return $embedding;
    }

    /**
     * @param  array<string>  $texts
     * @return array<array<float>>
     */
    public function embedBatch(array $texts): array
    {
        return array_map(fn (string $text): array => $this->embed($text), $texts);
    }
}
