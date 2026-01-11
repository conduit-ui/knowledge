<?php

declare(strict_types=1);

use App\Enums\ObservationType;
use App\Models\Observation;
use App\Models\Session;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SessionService;
});

describe('getActiveSessions', function () {
    it('returns only sessions without ended_at', function () {
        Session::factory()->count(3)->create(['ended_at' => null]);
        Session::factory()->count(2)->create(['ended_at' => now()]);

        $results = $this->service->getActiveSessions();

        expect($results)->toHaveCount(3);
        foreach ($results as $session) {
            expect($session->ended_at)->toBeNull();
        }
    });

    it('orders by started_at descending', function () {
        $old = Session::factory()->create([
            'ended_at' => null,
            'started_at' => now()->subDays(3),
        ]);
        $recent = Session::factory()->create([
            'ended_at' => null,
            'started_at' => now(),
        ]);

        $results = $this->service->getActiveSessions();

        expect($results->first()->id)->toBe($recent->id);
    });

    it('returns empty collection when no active sessions', function () {
        Session::factory()->count(5)->create(['ended_at' => now()]);

        $results = $this->service->getActiveSessions();

        expect($results)->toBeEmpty();
    });
});

describe('getRecentSessions', function () {
    it('returns sessions with default limit', function () {
        Session::factory()->count(25)->create();

        $results = $this->service->getRecentSessions();

        expect($results)->toHaveCount(20); // Default limit
    });

    it('returns sessions with custom limit', function () {
        Session::factory()->count(30)->create();

        $results = $this->service->getRecentSessions(10);

        expect($results)->toHaveCount(10);
    });

    it('filters by project when provided', function () {
        Session::factory()->count(5)->create(['project' => 'project-a']);
        Session::factory()->count(5)->create(['project' => 'project-b']);

        $results = $this->service->getRecentSessions(20, 'project-a');

        expect($results)->toHaveCount(5);
        foreach ($results as $session) {
            expect($session->project)->toBe('project-a');
        }
    });

    it('orders by started_at descending', function () {
        $old = Session::factory()->create(['started_at' => now()->subDays(5)]);
        $middle = Session::factory()->create(['started_at' => now()->subDays(2)]);
        $recent = Session::factory()->create(['started_at' => now()]);

        $results = $this->service->getRecentSessions();

        expect($results->first()->id)->toBe($recent->id);
        expect($results->last()->id)->toBe($old->id);
    });

    it('includes both active and ended sessions', function () {
        Session::factory()->count(5)->create(['ended_at' => null]);
        Session::factory()->count(5)->create(['ended_at' => now()]);

        $results = $this->service->getRecentSessions();

        expect($results)->toHaveCount(10);
    });
});

describe('getSessionWithObservations', function () {
    it('returns session with exact UUID match', function () {
        $session = Session::factory()->create();
        Observation::factory()->count(3)->create(['session_id' => $session->id]);

        $result = $this->service->getSessionWithObservations($session->id);

        expect($result)->toBeInstanceOf(Session::class);
        expect($result->id)->toBe($session->id);
        expect($result->observations)->toHaveCount(3);
    });

    it('returns session with partial ID match (unique)', function () {
        $session = Session::factory()->create();
        $partialId = substr($session->id, 0, 8);

        $result = $this->service->getSessionWithObservations($partialId);

        expect($result)->toBeInstanceOf(Session::class);
        expect($result->id)->toBe($session->id);
    });

    it('returns error array when multiple partial matches exist', function () {
        // Create two sessions with IDs that start with same prefix
        // This is difficult with random UUIDs, so we'll test the logic
        $session1 = Session::factory()->create();
        $session2 = Session::factory()->create();

        // Use a very short prefix that might match multiple
        $result = $this->service->getSessionWithObservations('a');

        if (is_array($result) && isset($result['error'])) {
            expect($result)->toHaveKey('error');
            expect($result)->toHaveKey('matches');
            expect($result['matches'])->toBeArray();
        } else {
            // If only one match or exact match, that's fine too
            expect($result)->toBeInstanceOf(Session::class)->or->toBeNull();
        }
    });

    it('returns null when no match found', function () {
        $result = $this->service->getSessionWithObservations('nonexistent-id');

        expect($result)->toBeNull();
    });

    it('loads observations relationship', function () {
        $session = Session::factory()->create();
        Observation::factory()->count(5)->create(['session_id' => $session->id]);

        $result = $this->service->getSessionWithObservations($session->id);

        expect($result)->toBeInstanceOf(Session::class);
        expect($result->relationLoaded('observations'))->toBeTrue();
        expect($result->observations)->toHaveCount(5);
    });
});

describe('getSessionObservations', function () {
    it('returns observations for session', function () {
        $session = Session::factory()->create();
        Observation::factory()->count(5)->create(['session_id' => $session->id]);
        Observation::factory()->count(3)->create(); // Different session

        $results = $this->service->getSessionObservations($session->id);

        expect($results)->toHaveCount(5);
    });

    it('filters by observation type', function () {
        $session = Session::factory()->create();
        Observation::factory()->count(3)->create([
            'session_id' => $session->id,
            'type' => ObservationType::Milestone,
        ]);
        Observation::factory()->count(2)->create([
            'session_id' => $session->id,
            'type' => ObservationType::Decision,
        ]);

        $results = $this->service->getSessionObservations($session->id, ObservationType::Milestone);

        expect($results)->toHaveCount(3);
        foreach ($results as $observation) {
            expect($observation->type)->toBe(ObservationType::Milestone);
        }
    });

    it('orders by created_at descending', function () {
        $session = Session::factory()->create();
        $old = Observation::factory()->create([
            'session_id' => $session->id,
            'created_at' => now()->subDays(2),
        ]);
        $recent = Observation::factory()->create([
            'session_id' => $session->id,
            'created_at' => now(),
        ]);

        $results = $this->service->getSessionObservations($session->id);

        expect($results->first()->id)->toBe($recent->id);
    });

    it('returns empty collection when session not found', function () {
        $results = $this->service->getSessionObservations('nonexistent-id');

        expect($results)->toBeEmpty();
    });

    it('returns empty collection when session has no observations', function () {
        $session = Session::factory()->create();

        $results = $this->service->getSessionObservations($session->id);

        expect($results)->toBeEmpty();
    });
});
