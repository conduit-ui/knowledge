<?php

declare(strict_types=1);

namespace App\Integrations\Qdrant\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * @codeCoverageIgnore Qdrant API request DTO - tested via integration
 */
class ScrollPoints extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  array<string, mixed>|null  $filter
     */
    public function __construct(
        private readonly string $collectionName,
        private readonly int $limit = 20,
        private readonly ?array $filter = null,
        private readonly string|int|null $offset = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/collections/{$this->collectionName}/points/scroll";
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $body = [
            'limit' => $this->limit,
            'with_payload' => true,
            'with_vector' => false,
        ];

        if ($this->filter !== null) {
            $body['filter'] = $this->filter;
        }

        if ($this->offset !== null) {
            $body['offset'] = $this->offset;
        }

        return $body;
    }
}
