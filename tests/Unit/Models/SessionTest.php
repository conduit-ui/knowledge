<?php

declare(strict_types=1);

use App\Models\Observation;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Session model', function () {
    it('can be created with factory', function () {
        $session = Session::factory()->create();

        expect($session)->toBeInstanceOf(Session::class);
        expect($session->id)->toBeString();
        expect($session->project)->toBeString();
    });

    it('uses UUID for id', function () {
        $session = Session::factory()->create();

        expect($session->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
    });

    it('has fillable attributes', function () {
        $data = [
            'project' => 'test-project',
            'branch' => 'feature/test',
            'started_at' => now(),
            'ended_at' => now()->addHour(),
            'summary' => 'Test session summary',
        ];

        $session = Session::factory()->create($data);

        expect($session->project)->toBe('test-project');
        expect($session->branch)->toBe('feature/test');
        expect($session->summary)->toBe('Test session summary');
    });

    it('casts started_at to datetime', function () {
        $session = Session::factory()->create([
            'started_at' => '2025-01-01 10:00:00',
        ]);

        expect($session->started_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('casts ended_at to datetime', function () {
        $session = Session::factory()->create([
            'ended_at' => '2025-01-01 12:00:00',
        ]);

        expect($session->ended_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('allows null ended_at for active sessions', function () {
        $session = Session::factory()->create([
            'ended_at' => null,
        ]);

        expect($session->ended_at)->toBeNull();
    });

    it('allows null branch', function () {
        $session = Session::factory()->create([
            'branch' => null,
        ]);

        expect($session->branch)->toBeNull();
    });

    it('allows null summary', function () {
        $session = Session::factory()->create([
            'summary' => null,
        ]);

        expect($session->summary)->toBeNull();
    });

    it('has observations relationship', function () {
        $session = Session::factory()->create();
        Observation::factory()->count(3)->create([
            'session_id' => $session->id,
        ]);

        expect($session->observations)->toHaveCount(3);
        expect($session->observations->first())->toBeInstanceOf(Observation::class);
    });

    it('has hasMany relationship with observations', function () {
        $session = Session::factory()->create();

        expect($session->observations())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('automatically sets created_at and updated_at', function () {
        $session = Session::factory()->create();

        expect($session->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($session->updated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('can be updated', function () {
        $session = Session::factory()->create([
            'summary' => 'Original summary',
        ]);

        $session->update(['summary' => 'Updated summary']);

        expect($session->fresh()->summary)->toBe('Updated summary');
    });

    it('can be deleted', function () {
        $session = Session::factory()->create();
        $id = $session->id;

        $session->delete();

        expect(Session::find($id))->toBeNull();
    });

    it('can have multiple observations', function () {
        $session = Session::factory()->create();
        Observation::factory()->count(10)->create([
            'session_id' => $session->id,
        ]);

        expect($session->observations()->count())->toBe(10);
    });

    it('stores project name correctly', function () {
        $session = Session::factory()->create([
            'project' => 'conduit-ui/knowledge',
        ]);

        expect($session->project)->toBe('conduit-ui/knowledge');
    });

    it('stores branch name correctly', function () {
        $session = Session::factory()->create([
            'branch' => 'feature/qdrant-integration',
        ]);

        expect($session->branch)->toBe('feature/qdrant-integration');
    });
});
