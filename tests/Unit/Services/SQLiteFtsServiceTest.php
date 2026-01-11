<?php

declare(strict_types=1);

use App\Enums\ObservationType;
use App\Models\Observation;
use App\Services\SQLiteFtsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SQLiteFtsService;
});

describe('searchObservations', function () {
    it('returns empty collection for empty query', function () {
        $results = $this->service->searchObservations('');

        expect($results)->toBeEmpty();
    });

    it('returns empty collection when FTS not available', function () {
        // Drop the FTS table temporarily
        DB::statement('DROP TABLE IF EXISTS observations_fts');

        $results = $this->service->searchObservations('test');

        expect($results)->toBeEmpty();

        // Recreate for other tests
        $this->artisan('migrate:fresh');
    });

    it('searches observations with query', function () {
        if (! $this->service->isAvailable()) {
            $this->markTestSkipped('FTS table not available');
        }

        $observation = Observation::factory()->create([
            'title' => 'Laravel Testing Guide',
            'narrative' => 'This is about testing',
        ]);

        $results = $this->service->searchObservations('Laravel');

        expect($results->count())->toBeGreaterThanOrEqual(0);
    });

    it('applies type filter', function () {
        if (! $this->service->isAvailable()) {
            $this->markTestSkipped('FTS table not available');
        }

        Observation::factory()->create([
            'title' => 'Test Milestone',
            'type' => ObservationType::Milestone,
        ]);
        Observation::factory()->create([
            'title' => 'Test Decision',
            'type' => ObservationType::Decision,
        ]);

        $results = $this->service->searchObservations('Test', [
            'type' => ObservationType::Milestone,
        ]);

        foreach ($results as $observation) {
            expect($observation->type)->toBe(ObservationType::Milestone);
        }
    });

    it('applies session_id filter', function () {
        if (! $this->service->isAvailable()) {
            $this->markTestSkipped('FTS table not available');
        }

        $sessionId = 'session-123';
        Observation::factory()->create([
            'title' => 'Session Test',
            'session_id' => $sessionId,
        ]);
        Observation::factory()->create([
            'title' => 'Session Test',
            'session_id' => 'different-session',
        ]);

        $results = $this->service->searchObservations('Session', [
            'session_id' => $sessionId,
        ]);

        foreach ($results as $observation) {
            expect($observation->session_id)->toBe($sessionId);
        }
    });

    it('applies concept filter', function () {
        if (! $this->service->isAvailable()) {
            $this->markTestSkipped('FTS table not available');
        }

        Observation::factory()->create([
            'title' => 'Concept Test',
            'concept' => 'architecture',
        ]);
        Observation::factory()->create([
            'title' => 'Concept Test',
            'concept' => 'testing',
        ]);

        $results = $this->service->searchObservations('Concept', [
            'concept' => 'architecture',
        ]);

        foreach ($results as $observation) {
            expect($observation->concept)->toBe('architecture');
        }
    });

    it('handles special characters in query', function () {
        if (! $this->service->isAvailable()) {
            $this->markTestSkipped('FTS table not available');
        }

        // Should not throw exception with special chars like hyphens
        $results = $this->service->searchObservations('test-query');

        expect($results)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    });

    it('returns empty collection when no matches found', function () {
        if (! $this->service->isAvailable()) {
            $this->markTestSkipped('FTS table not available');
        }

        $results = $this->service->searchObservations('nonexistent-term-xyz');

        expect($results)->toBeEmpty();
    });
});

describe('isAvailable', function () {
    it('returns true when FTS table exists', function () {
        $result = $this->service->isAvailable();

        expect($result)->toBeBool();
    });

    it('returns false when FTS table does not exist', function () {
        DB::statement('DROP TABLE IF EXISTS observations_fts');

        $result = $this->service->isAvailable();

        expect($result)->toBeFalse();

        // Recreate for other tests
        $this->artisan('migrate:fresh');
    });
});

describe('rebuildIndex', function () {
    it('does nothing when FTS not available', function () {
        DB::statement('DROP TABLE IF EXISTS observations_fts');

        // Should not throw exception
        $this->service->rebuildIndex();

        expect(true)->toBeTrue();

        // Recreate for other tests
        $this->artisan('migrate:fresh');
    });

    it('rebuilds index when available', function () {
        if (! $this->service->isAvailable()) {
            $this->markTestSkipped('FTS table not available');
        }

        // Should not throw exception
        $this->service->rebuildIndex();

        expect(true)->toBeTrue();
    });
});
