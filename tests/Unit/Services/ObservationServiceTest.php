<?php

declare(strict_types=1);

use App\Enums\ObservationType;
use App\Models\Observation;
use App\Models\Session;
use App\Services\ObservationService;
use App\Services\StubFtsService;

describe('ObservationService', function (): void {
    beforeEach(function (): void {
        $this->ftsService = new StubFtsService;
        $this->service = new ObservationService($this->ftsService);
    });

    describe('createObservation', function (): void {
        it('creates an observation with required fields', function (): void {
            $session = Session::factory()->create();

            $data = [
                'session_id' => $session->id,
                'type' => ObservationType::Discovery,
                'title' => 'Test Observation',
                'narrative' => 'This is a test observation',
            ];

            $observation = $this->service->createObservation($data);

            expect($observation)->toBeInstanceOf(Observation::class);
            expect($observation->session_id)->toBe($session->id);
            expect($observation->type)->toBe(ObservationType::Discovery);
            expect($observation->title)->toBe('Test Observation');
            expect($observation->narrative)->toBe('This is a test observation');
            expect($observation->exists)->toBeTrue();
        });

        it('creates an observation with all fields', function (): void {
            $session = Session::factory()->create();

            $data = [
                'session_id' => $session->id,
                'type' => ObservationType::Feature,
                'concept' => 'Authentication',
                'title' => 'Feature Observation',
                'subtitle' => 'Added OAuth support',
                'narrative' => 'Implemented OAuth 2.0 authentication',
                'facts' => ['provider' => 'Google', 'scopes' => ['email', 'profile']],
                'files_read' => ['app/Http/Controllers/AuthController.php'],
                'files_modified' => ['config/services.php', 'routes/web.php'],
                'tools_used' => ['artisan', 'composer'],
                'work_tokens' => 1500,
                'read_tokens' => 3000,
            ];

            $observation = $this->service->createObservation($data);

            expect($observation->session_id)->toBe($session->id);
            expect($observation->type)->toBe(ObservationType::Feature);
            expect($observation->concept)->toBe('Authentication');
            expect($observation->title)->toBe('Feature Observation');
            expect($observation->subtitle)->toBe('Added OAuth support');
            expect($observation->narrative)->toBe('Implemented OAuth 2.0 authentication');
            expect($observation->facts)->toBe(['provider' => 'Google', 'scopes' => ['email', 'profile']]);
            expect($observation->files_read)->toBe(['app/Http/Controllers/AuthController.php']);
            expect($observation->files_modified)->toBe(['config/services.php', 'routes/web.php']);
            expect($observation->tools_used)->toBe(['artisan', 'composer']);
            expect($observation->work_tokens)->toBe(1500);
            expect($observation->read_tokens)->toBe(3000);
        });

        it('creates an observation with default work and read tokens', function (): void {
            $session = Session::factory()->create();

            $data = [
                'session_id' => $session->id,
                'type' => ObservationType::Discovery,
                'title' => 'Test',
                'narrative' => 'Test narrative',
            ];

            $observation = $this->service->createObservation($data);

            expect($observation->work_tokens)->toBe(0);
            expect($observation->read_tokens)->toBe(0);
        });
    });

    describe('searchObservations', function (): void {
        it('searches observations using FTS service', function (): void {
            $session = Session::factory()->create();
            Observation::factory()->create([
                'session_id' => $session->id,
                'title' => 'Authentication Bug Fix',
                'type' => ObservationType::Bugfix,
            ]);

            // StubFtsService returns empty collection, so we expect empty result
            $results = $this->service->searchObservations('authentication', []);

            expect($results)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
        });

        it('passes filters to FTS service', function (): void {
            $filters = [
                'type' => ObservationType::Feature->value,
                'concept' => 'Authentication',
            ];

            $results = $this->service->searchObservations('oauth', $filters);

            expect($results)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
        });
    });

    describe('getObservationsByType', function (): void {
        it('returns observations filtered by type', function (): void {
            $session = Session::factory()->create();

            Observation::factory()->create([
                'session_id' => $session->id,
                'type' => ObservationType::Feature,
                'title' => 'Feature 1',
            ]);

            Observation::factory()->create([
                'session_id' => $session->id,
                'type' => ObservationType::Bugfix,
                'title' => 'Bug 1',
            ]);

            Observation::factory()->create([
                'session_id' => $session->id,
                'type' => ObservationType::Feature,
                'title' => 'Feature 2',
            ]);

            $results = $this->service->getObservationsByType(ObservationType::Feature, 10);

            expect($results)->toHaveCount(2);
            expect($results->first()->type)->toBe(ObservationType::Feature);
        });

        it('respects the limit parameter', function (): void {
            $session = Session::factory()->create();

            Observation::factory(5)->create([
                'session_id' => $session->id,
                'type' => ObservationType::Discovery,
            ]);

            $results = $this->service->getObservationsByType(ObservationType::Discovery, 3);

            expect($results)->toHaveCount(3);
        });

        it('returns empty collection when no observations match type', function (): void {
            $results = $this->service->getObservationsByType(ObservationType::Refactor, 10);

            expect($results)->toHaveCount(0);
        });
    });

    describe('getRecentObservations', function (): void {
        it('returns recent observations in descending order', function (): void {
            $session = Session::factory()->create();

            $old = Observation::factory()->create([
                'session_id' => $session->id,
                'title' => 'Old Observation',
                'created_at' => now()->subDays(5),
            ]);

            $recent = Observation::factory()->create([
                'session_id' => $session->id,
                'title' => 'Recent Observation',
                'created_at' => now()->subDays(1),
            ]);

            $newest = Observation::factory()->create([
                'session_id' => $session->id,
                'title' => 'Newest Observation',
                'created_at' => now(),
            ]);

            $results = $this->service->getRecentObservations(10);

            expect($results)->toHaveCount(3);
            expect($results->first()->id)->toBe($newest->id);
            expect($results->last()->id)->toBe($old->id);
        });

        it('respects the limit parameter', function (): void {
            $session = Session::factory()->create();

            Observation::factory(10)->create([
                'session_id' => $session->id,
            ]);

            $results = $this->service->getRecentObservations(5);

            expect($results)->toHaveCount(5);
        });

        it('returns empty collection when no observations exist', function (): void {
            $results = $this->service->getRecentObservations(10);

            expect($results)->toHaveCount(0);
        });
    });
});
