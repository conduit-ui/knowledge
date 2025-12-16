<?php

declare(strict_types=1);

use App\Enums\ObservationType;
use App\Models\Observation;
use App\Models\Session;
use App\Services\SQLiteFtsService;

uses()->beforeEach(fn () => $this->service = new SQLiteFtsService);

describe('SQLiteFtsService', function (): void {
    describe('searchObservations', function (): void {
        it('returns empty collection for empty query', function (): void {
            $results = $this->service->searchObservations('');

            expect($results)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
            expect($results)->toHaveCount(0);
        });

        it('returns matching observations by title', function (): void {
            $session = Session::factory()->create();

            Observation::factory()->forSession($session)->create([
                'title' => 'Laravel testing with Pest',
                'narrative' => 'Details about testing',
                'type' => ObservationType::Feature,
            ]);

            Observation::factory()->forSession($session)->create([
                'title' => 'Database migration script',
                'narrative' => 'Migration details',
                'type' => ObservationType::Decision,
            ]);

            $results = $this->service->searchObservations('Laravel');

            expect($results)->toHaveCount(1);
            expect($results->first()->title)->toContain('Laravel');
        });

        it('searches across narrative field', function (): void {
            $session = Session::factory()->create();

            Observation::factory()->forSession($session)->create([
                'title' => 'Authentication Setup',
                'narrative' => 'Implemented OAuth2 authentication flow',
                'type' => ObservationType::Feature,
            ]);

            Observation::factory()->forSession($session)->create([
                'title' => 'Database Config',
                'narrative' => 'Database connection pooling',
                'type' => ObservationType::Decision,
            ]);

            $results = $this->service->searchObservations('OAuth2');

            expect($results)->toHaveCount(1);
            expect($results->first()->narrative)->toContain('OAuth2');
        });

        it('searches across multiple observations', function (): void {
            $session = Session::factory()->create();

            Observation::factory()->forSession($session)->create([
                'title' => 'Testing authentication flow',
                'narrative' => 'Auth tests',
                'type' => ObservationType::Feature,
            ]);

            Observation::factory()->forSession($session)->create([
                'title' => 'Testing database queries',
                'narrative' => 'Query tests',
                'type' => ObservationType::Decision,
            ]);

            Observation::factory()->forSession($session)->create([
                'title' => 'User authentication setup',
                'narrative' => 'Setup auth',
                'type' => ObservationType::Bugfix,
            ]);

            // "authentication" appears in 2 observations (first and third)
            // "Testing" appears in 2 observations (first and second)
            $results = $this->service->searchObservations('Testing');

            expect($results)->toHaveCount(2);
        });

        it('respects type filter', function (): void {
            $session = Session::factory()->create();

            Observation::factory()->forSession($session)->create([
                'title' => 'Authentication milestone',
                'narrative' => 'Milestone reached',
                'type' => ObservationType::Feature,
            ]);

            Observation::factory()->forSession($session)->create([
                'title' => 'Authentication decision',
                'narrative' => 'Decision made',
                'type' => ObservationType::Decision,
            ]);

            $results = $this->service->searchObservations('authentication', ['type' => ObservationType::Feature]);

            expect($results)->toHaveCount(1);
            expect($results->first()->type)->toBe(ObservationType::Feature);
        });

        it('respects session_id filter', function (): void {
            $session1 = Session::factory()->create();
            $session2 = Session::factory()->create();

            Observation::factory()->forSession($session1)->create([
                'title' => 'Session observation',
                'narrative' => 'First session work',
                'type' => ObservationType::Feature,
            ]);

            Observation::factory()->forSession($session2)->create([
                'title' => 'Different session observation',
                'narrative' => 'Second session work',
                'type' => ObservationType::Feature,
            ]);

            $results = $this->service->searchObservations('observation', ['session_id' => $session1->id]);

            expect($results)->toHaveCount(1);
            expect($results->first()->session_id)->toBe($session1->id);
        });

        it('respects concept filter', function (): void {
            $session = Session::factory()->create();

            Observation::factory()->forSession($session)->create([
                'title' => 'Database query optimization',
                'narrative' => 'Optimized queries',
                'type' => ObservationType::Feature,
                'concept' => 'database',
            ]);

            Observation::factory()->forSession($session)->create([
                'title' => 'Database schema design',
                'narrative' => 'Schema design',
                'type' => ObservationType::Decision,
                'concept' => 'architecture',
            ]);

            $results = $this->service->searchObservations('database', ['concept' => 'database']);

            expect($results)->toHaveCount(1);
            expect($results->first()->concept)->toBe('database');
        });

        it('applies multiple filters', function (): void {
            $session1 = Session::factory()->create();
            $session2 = Session::factory()->create();

            Observation::factory()->forSession($session1)->create([
                'title' => 'Testing authentication flow',
                'narrative' => 'Auth tests',
                'type' => ObservationType::Feature,
                'concept' => 'authentication',
            ]);

            Observation::factory()->forSession($session1)->create([
                'title' => 'Testing database queries',
                'narrative' => 'Query tests',
                'type' => ObservationType::Decision,
                'concept' => 'database',
            ]);

            Observation::factory()->forSession($session2)->create([
                'title' => 'Testing API endpoints',
                'narrative' => 'API tests',
                'type' => ObservationType::Feature,
                'concept' => 'api',
            ]);

            $results = $this->service->searchObservations('testing', [
                'type' => ObservationType::Feature,
                'session_id' => $session1->id,
            ]);

            expect($results)->toHaveCount(1);
            expect($results->first()->concept)->toBe('authentication');
        });

        it('returns results ordered by relevance', function (): void {
            $session = Session::factory()->create();

            Observation::factory()->forSession($session)->create([
                'title' => 'Laravel framework testing Laravel Laravel',
                'narrative' => 'Testing Laravel',
                'type' => ObservationType::Feature,
            ]);

            Observation::factory()->forSession($session)->create([
                'title' => 'Laravel basics',
                'narrative' => 'Basic intro',
                'type' => ObservationType::Decision,
            ]);

            $results = $this->service->searchObservations('Laravel');

            expect($results)->toHaveCount(2);
            // First result should have more matches
            expect($results->first()->title)->toContain('framework');
        });

        it('returns empty collection when no matches found', function (): void {
            $session = Session::factory()->create();

            Observation::factory()->forSession($session)->create([
                'title' => 'Database migration',
                'narrative' => 'Migration script',
                'type' => ObservationType::Feature,
            ]);

            $results = $this->service->searchObservations('nonexistent');

            expect($results)->toHaveCount(0);
        });

        it('returns empty collection when FTS table does not exist', function (): void {
            // Drop the FTS table to simulate unavailable FTS
            DB::statement('DROP TABLE IF EXISTS observations_fts');

            $results = $this->service->searchObservations('test query');

            expect($results)->toHaveCount(0);
        });

        it('searches concept field', function (): void {
            $session = Session::factory()->create();

            Observation::factory()->forSession($session)->create([
                'title' => 'API Implementation',
                'narrative' => 'REST API work',
                'type' => ObservationType::Feature,
                'concept' => 'api-design',
            ]);

            Observation::factory()->forSession($session)->create([
                'title' => 'Database Work',
                'narrative' => 'Schema updates',
                'type' => ObservationType::Feature,
                'concept' => 'database',
            ]);

            $results = $this->service->searchObservations('api-design');

            expect($results)->toHaveCount(1);
            expect($results->first()->concept)->toBe('api-design');
        });

        it('searches subtitle field', function (): void {
            $session = Session::factory()->create();

            Observation::factory()->forSession($session)->create([
                'title' => 'Feature Implementation',
                'subtitle' => 'Payment gateway integration',
                'narrative' => 'Implemented Stripe',
                'type' => ObservationType::Feature,
            ]);

            Observation::factory()->forSession($session)->create([
                'title' => 'Other Work',
                'subtitle' => 'User interface updates',
                'narrative' => 'UI changes',
                'type' => ObservationType::Feature,
            ]);

            $results = $this->service->searchObservations('Payment');

            expect($results)->toHaveCount(1);
            expect($results->first()->subtitle)->toContain('Payment');
        });
    });

    describe('isAvailable', function (): void {
        it('returns true when FTS table exists', function (): void {
            expect($this->service->isAvailable())->toBeTrue();
        });

        it('returns false when table does not exist', function (): void {
            // Drop the FTS table to test the case where isAvailable returns false
            DB::statement('DROP TABLE IF EXISTS observations_fts');

            expect($this->service->isAvailable())->toBeFalse();
        });
    });

    describe('rebuildIndex', function (): void {
        it('rebuilds index without error', function (): void {
            Observation::factory()->count(5)->create();

            expect(fn () => $this->service->rebuildIndex())->not->toThrow(Exception::class);
        });

        it('does nothing when FTS table does not exist', function (): void {
            // Drop the FTS table
            DB::statement('DROP TABLE IF EXISTS observations_fts');

            expect(fn () => $this->service->rebuildIndex())->not->toThrow(Exception::class);
        });

    });
});
