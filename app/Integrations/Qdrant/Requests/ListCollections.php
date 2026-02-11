<?php

declare(strict_types=1);

namespace App\Integrations\Qdrant\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class ListCollections extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/collections';
    }
}
