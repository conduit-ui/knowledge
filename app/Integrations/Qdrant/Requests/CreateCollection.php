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
    ) {}

    public function resolveEndpoint(): string
    {
        return "/collections/{$this->collectionName}";
    }

    protected function defaultBody(): array
    {
        return [
            'vectors' => [
                'size' => $this->vectorSize,
                'distance' => $this->distance,
            ],
            // Enable payload indexing for fast metadata filtering
            'optimizers_config' => [
                'indexing_threshold' => 20000,
            ],
        ];
    }
}
