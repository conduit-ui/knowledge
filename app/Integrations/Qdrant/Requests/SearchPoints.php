<?php

declare(strict_types=1);

namespace App\Integrations\Qdrant\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class SearchPoints extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  string  $collectionName
     * @param  array<float>  $vector
     * @param  int  $limit
     * @param  float  $scoreThreshold
     * @param  array<string, mixed>|null  $filter
     */
    public function __construct(
        protected readonly string $collectionName,
        protected readonly array $vector,
        protected readonly int $limit = 20,
        protected readonly float $scoreThreshold = 0.7,
        protected readonly ?array $filter = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/collections/{$this->collectionName}/points/search";
    }

    protected function defaultBody(): array
    {
        $body = [
            'vector' => $this->vector,
            'limit' => $this->limit,
            'score_threshold' => $this->scoreThreshold,
            'with_payload' => true,
            'with_vector' => false, // Don't return vectors in results (save bandwidth)
        ];

        if ($this->filter) {
            $body['filter'] = $this->filter;
        }

        return $body;
    }
}
