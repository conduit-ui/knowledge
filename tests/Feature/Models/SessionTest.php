<?php

declare(strict_types=1);

use App\Models\Observation;
use App\Models\Session;

describe('Session Model', function (): void {
    it('can be created with factory', function (): void {
        $session = Session::factory()->create();

        expect($session)->toBeInstanceOf(Session::class);
        expect($session->id)->toBeString();
        expect($session->project)->toBeString();
    });

    it('uses uuid as primary key', function (): void {
        $session = Session::factory()->create();

        expect($session->getKeyType())->toBe('string');
        expect($session->getIncrementing())->toBeFalse();
        expect(strlen($session->id))->toBe(36); // UUID format
    });

    it('has fillable attributes', function (): void {
        $session = Session::factory()->create([
            'project' => 'my-project',
            'branch' => 'feature/test',
            'summary' => 'Test summary',
        ]);

        expect($session->project)->toBe('my-project');
        expect($session->branch)->toBe('feature/test');
        expect($session->summary)->toBe('Test summary');
    });

    it('casts started_at and ended_at to datetime', function (): void {
        $session = Session::factory()->completed()->create();

        expect($session->started_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($session->ended_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('has many observations', function (): void {
        $session = Session::factory()->create();

        Observation::factory()->count(3)->forSession($session)->create();

        expect($session->observations)->toHaveCount(3);
        expect($session->observations->first())->toBeInstanceOf(Observation::class);
    });

    it('can be created as active session', function (): void {
        $session = Session::factory()->active()->create();

        expect($session->ended_at)->toBeNull();
    });

    it('can be created as completed session', function (): void {
        $session = Session::factory()->completed()->create();

        expect($session->ended_at)->not->toBeNull();
        expect($session->summary)->not->toBeNull();
    });

    it('can be created for specific project', function (): void {
        $session = Session::factory()->forProject('conduit-core')->create();

        expect($session->project)->toBe('conduit-core');
    });

    it('allows null branch', function (): void {
        $session = Session::factory()->create(['branch' => null]);

        expect($session->branch)->toBeNull();
    });

    it('allows null summary', function (): void {
        $session = Session::factory()->create(['summary' => null]);

        expect($session->summary)->toBeNull();
    });

    it('deletes observations when session is deleted', function (): void {
        $session = Session::factory()->create();
        $observationIds = Observation::factory()->count(3)->forSession($session)->create()->pluck('id');

        expect(Observation::whereIn('id', $observationIds)->count())->toBe(3);

        $session->delete();

        expect(Observation::whereIn('id', $observationIds)->count())->toBe(0);
    });
});
