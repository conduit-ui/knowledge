<?php

declare(strict_types=1);

namespace App\Integrations\Qdrant\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class DeletePoints extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  string  $collectionName
     * @param  array<string|int>  $pointIds
     */
    public function __construct(
        protected readonly string $collectionName,
        protected readonly array $pointIds,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/collections/{$this->collectionName}/points/delete";
    }

    protected function defaultBody(): array
    {
        return [
            'points' => $this->pointIds,
        ];
    }
}
