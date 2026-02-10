<?php

declare(strict_types=1);

use App\Integrations\Qdrant\Requests\ListCollections;
use Saloon\Enums\Method;

describe('ListCollections', function (): void {
    it('has GET method', function (): void {
        $request = new ListCollections;

        expect($request->getMethod())->toBe(Method::GET);
    });

    it('resolves to /collections endpoint', function (): void {
        $request = new ListCollections;

        expect($request->resolveEndpoint())->toBe('/collections');
    });
});
