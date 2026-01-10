<?php

declare(strict_types=1);

use App\Enums\ObservationType;
use App\Services\StubFtsService;

beforeEach(function () {
    $this->service = new StubFtsService;
});

describe('searchObservations', function () {
    it('returns empty collection for any query', function () {
        $result = $this->service->searchObservations('test query');

        expect($result)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
        expect($result)->toBeEmpty();
    });

    it('returns empty collection for empty query', function () {
        $result = $this->service->searchObservations('');

        expect($result)->toBeEmpty();
    });

    it('returns empty collection with filters', function () {
        $filters = [
            'type' => 'milestone',
            'session_id' => 'session-123',
            'concept' => 'testing',
        ];

        $result = $this->service->searchObservations('query', $filters);

        expect($result)->toBeEmpty();
    });

    it('returns empty collection for complex queries', function () {
        $result = $this->service->searchObservations('complex "quoted" query -excluded');

        expect($result)->toBeEmpty();
    });
});

describe('isAvailable', function () {
    it('always returns false', function () {
        $result = $this->service->isAvailable();

        expect($result)->toBeFalse();
    });
});

describe('rebuildIndex', function () {
    it('does nothing and returns void', function () {
        // Should not throw any exception
        $this->service->rebuildIndex();

        expect(true)->toBeTrue();
    });

    it('can be called multiple times safely', function () {
        $this->service->rebuildIndex();
        $this->service->rebuildIndex();
        $this->service->rebuildIndex();

        expect(true)->toBeTrue();
    });
});
