<?php

declare(strict_types=1);

namespace App\Integrations\Qdrant\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Hybrid search using prefetch + RRF (Reciprocal Rank Fusion).
 *
 * Combines dense vector (semantic) and sparse vector (lexical/BM25) search
 * results using Qdrant's native RRF fusion for improved relevance.
 */
class HybridSearchPoints extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  array<float>  $denseVector  Dense embedding vector
     * @param  array{indices: array<int>, values: array<float>}  $sparseVector  Sparse embedding vector
     * @param  array<string, mixed>|null  $filter  Optional Qdrant filter
     */
    public function __construct(
        protected readonly string $collectionName,
        protected readonly array $denseVector,
        protected readonly array $sparseVector,
        protected readonly int $limit = 20,
        protected readonly int $prefetchLimit = 40,
        protected readonly ?array $filter = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/collections/{$this->collectionName}/points/query";
    }

    protected function defaultBody(): array
    {
        $prefetch = [
            [
                'query' => $this->denseVector,
                'using' => 'dense',
                'limit' => $this->prefetchLimit,
            ],
            [
                'query' => [
                    'indices' => $this->sparseVector['indices'],
                    'values' => $this->sparseVector['values'],
                ],
                'using' => 'sparse',
                'limit' => $this->prefetchLimit,
            ],
        ];

        // Add filter to each prefetch if provided
        if ($this->filter !== null) {
            foreach ($prefetch as &$p) {
                $p['filter'] = $this->filter;
            }
        }

        return [
            'prefetch' => $prefetch,
            'query' => ['fusion' => 'rrf'],
            'limit' => $this->limit,
            'with_payload' => true,
            'with_vector' => false,
        ];
    }
}
