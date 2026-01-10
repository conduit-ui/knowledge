<?php

declare(strict_types=1);

namespace App\Integrations\Qdrant\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class GetPoints extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  string  $collectionName
     * @param  array<string|int>  $ids
     */
    public function __construct(
        protected readonly string $collectionName,
        protected readonly array $ids,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/collections/{$this->collectionName}/points";
    }

    protected function defaultBody(): array
    {
        return [
            'ids' => $this->ids,
            'with_payload' => true,
            'with_vector' => false,
        ];
    }
}
