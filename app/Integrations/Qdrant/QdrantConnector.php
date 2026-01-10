<?php

declare(strict_types=1);

namespace App\Integrations\Qdrant;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class QdrantConnector extends Connector
{
    use AcceptsJson;

    public function __construct(
        protected readonly string $host,
        protected readonly int $port,
        protected readonly ?string $apiKey = null,
        protected readonly bool $secure = false,
    ) {}

    public function resolveBaseUrl(): string
    {
        $protocol = $this->secure ? 'https' : 'http';

        return "{$protocol}://{$this->host}:{$this->port}";
    }

    protected function defaultHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($this->apiKey) {
            $headers['api-key'] = $this->apiKey;
        }

        return $headers;
    }

    public function defaultConfig(): array
    {
        return [
            'timeout' => 30,
        ];
    }
}
