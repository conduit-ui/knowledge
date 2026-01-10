<?php

declare(strict_types=1);

namespace App\Integrations\Qdrant\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCollectionInfo extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $collectionName,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/collections/{$this->collectionName}";
    }
}
