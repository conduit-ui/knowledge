<?php

declare(strict_types=1);

namespace App\Integrations\Qdrant\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class CreateCollection extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        protected readonly string $collectionName,
        protected readonly int $vectorSize = 384, // all-MiniLM-L6-v2 default
        protected readonly string $distance = 'Cosine',
        protected readonly bool $hybridEnabled = false,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/collections/{$this->collectionName}";
    }

    protected function defaultBody(): array
    {
        if ($this->hybridEnabled) {
            return $this->buildHybridBody();
        }

        return $this->buildDenseOnlyBody();
    }

    /**
     * Build request body for dense-only vector configuration.
     *
     * @return array<string, mixed>
     */
    private function buildDenseOnlyBody(): array
    {
        return [
            'vectors' => [
                'size' => $this->vectorSize,
                'distance' => $this->distance,
            ],
            'optimizers_config' => [
                'indexing_threshold' => 20000,
            ],
        ];
    }

    /**
     * Build request body for hybrid search (dense + sparse vectors).
     *
     * @return array<string, mixed>
     */
    private function buildHybridBody(): array
    {
        return [
            'vectors' => [
                'dense' => [
                    'size' => $this->vectorSize,
                    'distance' => $this->distance,
                ],
            ],
            'sparse_vectors' => [
                'sparse' => [
                    'modifier' => 'idf',
                ],
            ],
            'optimizers_config' => [
                'indexing_threshold' => 20000,
            ],
        ];
    }
}
