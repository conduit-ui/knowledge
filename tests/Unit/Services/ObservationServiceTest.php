<?php

declare(strict_types=1);

use App\Contracts\FullTextSearchInterface;
use App\Enums\ObservationType;
use App\Models\Observation;
use App\Services\ObservationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mockFts = Mockery::mock(FullTextSearchInterface::class);
    $this->service = new ObservationService($this->mockFts);
});

afterEach(function () {
    Mockery::close();
});

describe('createObservation', function () {
    it('creates observation with provided data', function () {
        $data = [
            'type' => ObservationType::Milestone,
            'title' => 'Test Milestone',
            'subtitle' => 'Subtitle',
            'narrative' => 'Test narrative',
            'work_tokens' => 100,
            'read_tokens' => 50,
        ];

        $observation = $this->service->createObservation($data);

        expect($observation)->toBeInstanceOf(Observation::class);
        expect($observation->type)->toBe(ObservationType::Milestone);
        expect($observation->title)->toBe('Test Milestone');
        expect($observation->work_tokens)->toBe(100);
        expect($observation->read_tokens)->toBe(50);
    });

    it('sets default token values when not provided', function () {
        $data = [
            'type' => ObservationType::Decision,
            'title' => 'Test Decision',
        ];

        $observation = $this->service->createObservation($data);

        expect($observation->work_tokens)->toBe(0);
        expect($observation->read_tokens)->toBe(0);
    });

    it('preserves zero token values when explicitly provided', function () {
        $data = [
            'type' => ObservationType::Blocker,
            'title' => 'Test Blocker',
            'work_tokens' => 0,
            'read_tokens' => 0,
        ];

        $observation = $this->service->createObservation($data);

        expect($observation->work_tokens)->toBe(0);
        expect($observation->read_tokens)->toBe(0);
    });

    it('does not override provided token values', function () {
        $data = [
            'type' => ObservationType::Context,
            'title' => 'Test Context',
            'work_tokens' => 250,
            'read_tokens' => 125,
        ];

        $observation = $this->service->createObservation($data);

        expect($observation->work_tokens)->toBe(250);
        expect($observation->read_tokens)->toBe(125);
    });
});

describe('searchObservations', function () {
    it('delegates search to FTS service', function () {
        $query = 'test query';
        $filters = ['type' => ObservationType::Milestone];

        $obs1 = new Observation(['title' => 'Result 1']);
        $obs2 = new Observation(['title' => 'Result 2']);
        $expectedResults = new Collection([$obs1, $obs2]);

        $this->mockFts->shouldReceive('searchObservations')
            ->once()
            ->with($query, $filters)
            ->andReturn($expectedResults);

        $results = $this->service->searchObservations($query, $filters);

        expect($results)->toBe($expectedResults);
        expect($results)->toHaveCount(2);
    });

    it('passes empty filters to FTS service', function () {
        $query = 'search term';

        $this->mockFts->shouldReceive('searchObservations')
            ->once()
            ->with($query, [])
            ->andReturn(new Collection);

        $results = $this->service->searchObservations($query);

        expect($results)->toBeInstanceOf(Collection::class);
        expect($results)->toBeEmpty();
    });

    it('handles complex filters', function () {
        $query = 'complex search';
        $filters = [
            'type' => ObservationType::Decision,
            'session_id' => 'session-123',
            'concept' => 'architecture',
        ];

        $this->mockFts->shouldReceive('searchObservations')
            ->once()
            ->with($query, $filters)
            ->andReturn(new Collection);

        $results = $this->service->searchObservations($query, $filters);

        expect($results)->toBeInstanceOf(Collection::class);
    });
});

describe('getObservationsByType', function () {
    it('retrieves observations by type with default limit', function () {
        Observation::factory()->count(15)->create(['type' => ObservationType::Milestone]);
        Observation::factory()->count(5)->create(['type' => ObservationType::Decision]);

        $results = $this->service->getObservationsByType(ObservationType::Milestone);

        expect($results)->toHaveCount(10); // Default limit
        expect($results->first()->type)->toBe(ObservationType::Milestone);
    });

    it('retrieves observations with custom limit', function () {
        Observation::factory()->count(20)->create(['type' => ObservationType::Context]);

        $results = $this->service->getObservationsByType(ObservationType::Context, 5);

        expect($results)->toHaveCount(5);
    });

    it('orders observations by created_at descending', function () {
        $old = Observation::factory()->create([
            'type' => ObservationType::Blocker,
            'created_at' => now()->subDays(2),
        ]);
        $recent = Observation::factory()->create([
            'type' => ObservationType::Blocker,
            'created_at' => now(),
        ]);

        $results = $this->service->getObservationsByType(ObservationType::Blocker);

        expect($results->first()->id)->toBe($recent->id);
    });

    it('filters correctly by type', function () {
        Observation::factory()->count(5)->create(['type' => ObservationType::Milestone]);
        Observation::factory()->count(5)->create(['type' => ObservationType::Decision]);

        $results = $this->service->getObservationsByType(ObservationType::Milestone);

        foreach ($results as $observation) {
            expect($observation->type)->toBe(ObservationType::Milestone);
        }
    });

    it('returns empty collection when no observations of type exist', function () {
        Observation::factory()->count(5)->create(['type' => ObservationType::Milestone]);

        $results = $this->service->getObservationsByType(ObservationType::Blocker);

        expect($results)->toBeEmpty();
    });
});

describe('getRecentObservations', function () {
    it('retrieves recent observations with default limit', function () {
        Observation::factory()->count(15)->create();

        $results = $this->service->getRecentObservations();

        expect($results)->toHaveCount(10); // Default limit
    });

    it('retrieves observations with custom limit', function () {
        Observation::factory()->count(20)->create();

        $results = $this->service->getRecentObservations(5);

        expect($results)->toHaveCount(5);
    });

    it('orders observations by created_at descending', function () {
        $old = Observation::factory()->create(['created_at' => now()->subDays(3)]);
        $middle = Observation::factory()->create(['created_at' => now()->subDay()]);
        $recent = Observation::factory()->create(['created_at' => now()]);

        $results = $this->service->getRecentObservations(10);

        expect($results->first()->id)->toBe($recent->id);
        expect($results->last()->id)->toBe($old->id);
    });

    it('includes all observation types', function () {
        Observation::factory()->create(['type' => ObservationType::Milestone]);
        Observation::factory()->create(['type' => ObservationType::Decision]);
        Observation::factory()->create(['type' => ObservationType::Blocker]);
        Observation::factory()->create(['type' => ObservationType::Context]);

        $results = $this->service->getRecentObservations(10);

        expect($results)->toHaveCount(4);
    });

    it('returns empty collection when no observations exist', function () {
        $results = $this->service->getRecentObservations();

        expect($results)->toBeEmpty();
    });
});
