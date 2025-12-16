<?php

declare(strict_types=1);

use App\Enums\ObservationType;
use App\Models\Observation;
use App\Models\Session;
use App\Services\SessionService;

describe('SessionService', function (): void {
    beforeEach(function (): void {
        $this->service = new SessionService;
    });

    describe('getActiveSessions', function (): void {
        it('returns only sessions with null ended_at', function (): void {
            Session::factory()->create([
                'project' => 'project-a',
                'started_at' => now()->subHours(2),
                'ended_at' => null,
            ]);

            Session::factory()->create([
                'project' => 'project-b',
                'started_at' => now()->subHours(1),
                'ended_at' => now(),
            ]);

            Session::factory()->create([
                'project' => 'project-c',
                'started_at' => now()->subMinutes(30),
                'ended_at' => null,
            ]);

            $results = $this->service->getActiveSessions();

            expect($results)->toHaveCount(2);
            expect($results->first()->ended_at)->toBeNull();
            expect($results->last()->ended_at)->toBeNull();
        });

        it('returns sessions ordered by started_at descending', function (): void {
            $oldest = Session::factory()->create([
                'started_at' => now()->subHours(3),
                'ended_at' => null,
            ]);

            $middle = Session::factory()->create([
                'started_at' => now()->subHours(2),
                'ended_at' => null,
            ]);

            $newest = Session::factory()->create([
                'started_at' => now()->subHours(1),
                'ended_at' => null,
            ]);

            $results = $this->service->getActiveSessions();

            expect($results)->toHaveCount(3);
            expect($results->get(0)->id)->toBe($newest->id);
            expect($results->get(1)->id)->toBe($middle->id);
            expect($results->get(2)->id)->toBe($oldest->id);
        });

        it('returns empty collection when no active sessions exist', function (): void {
            Session::factory()->create([
                'ended_at' => now(),
            ]);

            $results = $this->service->getActiveSessions();

            expect($results)->toHaveCount(0);
        });
    });

    describe('getRecentSessions', function (): void {
        it('returns sessions ordered by started_at descending', function (): void {
            $oldest = Session::factory()->create([
                'started_at' => now()->subDays(3),
            ]);

            $middle = Session::factory()->create([
                'started_at' => now()->subDays(2),
            ]);

            $newest = Session::factory()->create([
                'started_at' => now()->subDays(1),
            ]);

            $results = $this->service->getRecentSessions(10);

            expect($results)->toHaveCount(3);
            expect($results->get(0)->id)->toBe($newest->id);
            expect($results->get(1)->id)->toBe($middle->id);
            expect($results->get(2)->id)->toBe($oldest->id);
        });

        it('respects the limit parameter', function (): void {
            Session::factory(10)->create();

            $results = $this->service->getRecentSessions(5);

            expect($results)->toHaveCount(5);
        });

        it('filters by project when provided', function (): void {
            Session::factory()->create([
                'project' => 'project-a',
            ]);

            Session::factory()->create([
                'project' => 'project-b',
            ]);

            Session::factory()->create([
                'project' => 'project-a',
            ]);

            $results = $this->service->getRecentSessions(10, 'project-a');

            expect($results)->toHaveCount(2);
            expect($results->first()->project)->toBe('project-a');
            expect($results->last()->project)->toBe('project-a');
        });

        it('does not filter by project when null', function (): void {
            Session::factory()->create(['project' => 'project-a']);
            Session::factory()->create(['project' => 'project-b']);
            Session::factory()->create(['project' => 'project-c']);

            $results = $this->service->getRecentSessions(10, null);

            expect($results)->toHaveCount(3);
        });

        it('returns empty collection when no sessions exist', function (): void {
            $results = $this->service->getRecentSessions(10);

            expect($results)->toHaveCount(0);
        });

        it('returns empty collection when no sessions match project filter', function (): void {
            Session::factory()->create(['project' => 'project-a']);
            Session::factory()->create(['project' => 'project-b']);

            $results = $this->service->getRecentSessions(10, 'project-c');

            expect($results)->toHaveCount(0);
        });
    });

    describe('getSessionWithObservations', function (): void {
        it('returns session with observations loaded', function (): void {
            $session = Session::factory()->create();
            Observation::factory(3)->create(['session_id' => $session->id]);

            $result = $this->service->getSessionWithObservations($session->id);

            expect($result)->not->toBeNull();
            expect($result->id)->toBe($session->id);
            expect($result->observations)->toHaveCount(3);
        });

        it('returns null when session does not exist', function (): void {
            $result = $this->service->getSessionWithObservations('non-existent-id');

            expect($result)->toBeNull();
        });

        it('returns session with empty observations collection when none exist', function (): void {
            $session = Session::factory()->create();

            $result = $this->service->getSessionWithObservations($session->id);

            expect($result)->not->toBeNull();
            expect($result->id)->toBe($session->id);
            expect($result->observations)->toHaveCount(0);
        });
    });

    describe('getSessionObservations', function (): void {
        it('returns observations for a session ordered by created_at descending', function (): void {
            $session = Session::factory()->create();

            $oldest = Observation::factory()->create([
                'session_id' => $session->id,
                'created_at' => now()->subHours(3),
            ]);

            $middle = Observation::factory()->create([
                'session_id' => $session->id,
                'created_at' => now()->subHours(2),
            ]);

            $newest = Observation::factory()->create([
                'session_id' => $session->id,
                'created_at' => now()->subHours(1),
            ]);

            $results = $this->service->getSessionObservations($session->id);

            expect($results)->toHaveCount(3);
            expect($results->get(0)->id)->toBe($newest->id);
            expect($results->get(1)->id)->toBe($middle->id);
            expect($results->get(2)->id)->toBe($oldest->id);
        });

        it('filters observations by type when provided', function (): void {
            $session = Session::factory()->create();

            Observation::factory()->create([
                'session_id' => $session->id,
                'type' => ObservationType::Feature,
            ]);

            Observation::factory()->create([
                'session_id' => $session->id,
                'type' => ObservationType::Bugfix,
            ]);

            Observation::factory()->create([
                'session_id' => $session->id,
                'type' => ObservationType::Feature,
            ]);

            $results = $this->service->getSessionObservations($session->id, ObservationType::Feature);

            expect($results)->toHaveCount(2);
            expect($results->first()->type)->toBe(ObservationType::Feature);
            expect($results->last()->type)->toBe(ObservationType::Feature);
        });

        it('returns all observations when type filter is null', function (): void {
            $session = Session::factory()->create();

            Observation::factory()->create([
                'session_id' => $session->id,
                'type' => ObservationType::Feature,
            ]);

            Observation::factory()->create([
                'session_id' => $session->id,
                'type' => ObservationType::Bugfix,
            ]);

            $results = $this->service->getSessionObservations($session->id, null);

            expect($results)->toHaveCount(2);
        });

        it('returns empty collection when session does not exist', function (): void {
            $results = $this->service->getSessionObservations('non-existent-id');

            expect($results)->toHaveCount(0);
        });

        it('returns empty collection when session has no observations', function (): void {
            $session = Session::factory()->create();

            $results = $this->service->getSessionObservations($session->id);

            expect($results)->toHaveCount(0);
        });

        it('returns empty collection when no observations match type filter', function (): void {
            $session = Session::factory()->create();

            Observation::factory()->create([
                'session_id' => $session->id,
                'type' => ObservationType::Feature,
            ]);

            $results = $this->service->getSessionObservations($session->id, ObservationType::Refactor);

            expect($results)->toHaveCount(0);
        });
    });
});
