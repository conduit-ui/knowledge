<?php

declare(strict_types=1);

namespace App\Integrations\Qdrant\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class UpsertPoints extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    /**
     * @param  array<int, array{id: string|int, vector: array<float>, payload: array<string, mixed>}>  $points
     */
    public function __construct(
        protected readonly string $collectionName,
        protected readonly array $points,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/collections/{$this->collectionName}/points";
    }

    protected function defaultBody(): array
    {
        return [
            'points' => $this->points,
        ];
    }
}
