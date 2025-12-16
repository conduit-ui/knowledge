<?php

declare(strict_types=1);

use App\Services\StubFtsService;

describe('StubFtsService', function (): void {
    beforeEach(function (): void {
        $this->service = new StubFtsService;
    });

    describe('searchObservations', function (): void {
        it('returns empty collection', function (): void {
            $results = $this->service->searchObservations('any query');

            expect($results)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
            expect($results)->toHaveCount(0);
        });

        it('returns empty collection with filters', function (): void {
            $results = $this->service->searchObservations('query', [
                'type' => 'milestone',
                'session_id' => 'test-session',
                'concept' => 'authentication',
            ]);

            expect($results)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
            expect($results)->toHaveCount(0);
        });

        it('returns empty collection for empty query', function (): void {
            $results = $this->service->searchObservations('');

            expect($results)->toHaveCount(0);
        });
    });

    describe('isAvailable', function (): void {
        it('returns false', function (): void {
            expect($this->service->isAvailable())->toBeFalse();
        });
    });

    describe('rebuildIndex', function (): void {
        it('is callable and does nothing', function (): void {
            expect(fn () => $this->service->rebuildIndex())->not->toThrow(Exception::class);
        });
    });
});
