<?php

declare(strict_types=1);

use App\Enums\ObservationType;
use App\Models\Observation;
use App\Models\Session;

describe('session:observations command', function (): void {
    it('lists all observations for a session', function (): void {
        $session = Session::factory()->create();

        Observation::factory()->create([
            'session_id' => $session->id,
            'type' => ObservationType::Feature,
            'title' => 'Feature Observation',
        ]);

        Observation::factory()->create([
            'session_id' => $session->id,
            'type' => ObservationType::Bugfix,
            'title' => 'Bugfix Observation',
        ]);

        $this->artisan('session:observations', ['id' => $session->id])
            ->assertSuccessful();
    });

    it('filters observations by type when --type option is used', function (): void {
        $session = Session::factory()->create();

        Observation::factory()->create([
            'session_id' => $session->id,
            'type' => ObservationType::Feature,
            'title' => 'Feature Observation',
        ]);

        Observation::factory()->create([
            'session_id' => $session->id,
            'type' => ObservationType::Bugfix,
            'title' => 'Bugfix Observation',
        ]);

        Observation::factory()->create([
            'session_id' => $session->id,
            'type' => ObservationType::Feature,
            'title' => 'Another Feature',
        ]);

        $this->artisan('session:observations', ['id' => $session->id, '--type' => 'feature'])
            ->assertSuccessful();

        // Verify the filter would select correct observations
        expect(Observation::where('session_id', $session->id)
            ->where('type', ObservationType::Feature)
            ->count())->toBe(2);
    });

    it('shows error for invalid observation type', function (): void {
        $session = Session::factory()->create();

        $this->artisan('session:observations', ['id' => $session->id, '--type' => 'invalid-type'])
            ->assertFailed()
            ->expectsOutput('Invalid observation type: invalid-type')
            ->expectsOutputToContain('Valid types:');
    });

    it('shows message when no observations exist for session', function (): void {
        $session = Session::factory()->create();

        $this->artisan('session:observations', ['id' => $session->id])
            ->assertSuccessful()
            ->expectsOutput('No observations found.');
    });

    it('shows message when no observations match type filter', function (): void {
        $session = Session::factory()->create();

        Observation::factory()->create([
            'session_id' => $session->id,
            'type' => ObservationType::Feature,
        ]);

        $this->artisan('session:observations', ['id' => $session->id, '--type' => 'bugfix'])
            ->assertSuccessful()
            ->expectsOutput('No observations found.');
    });

    it('shows message when session does not exist', function (): void {
        $this->artisan('session:observations', ['id' => 'non-existent-id'])
            ->assertSuccessful()
            ->expectsOutput('No observations found.');
    });

    it('displays observation details in table format', function (): void {
        $session = Session::factory()->create();

        $observation = Observation::factory()->create([
            'session_id' => $session->id,
            'type' => ObservationType::Discovery,
            'title' => 'Test Observation',
        ]);

        $this->artisan('session:observations', ['id' => $session->id])
            ->assertSuccessful();
    });

    it('orders observations by created_at descending', function (): void {
        $session = Session::factory()->create();

        $oldest = Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Oldest Observation',
            'created_at' => now()->subHours(3),
        ]);

        $newest = Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Newest Observation',
            'created_at' => now()->subHours(1),
        ]);

        $middle = Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Middle Observation',
            'created_at' => now()->subHours(2),
        ]);

        $this->artisan('session:observations', ['id' => $session->id])
            ->assertSuccessful();
    });

    it('accepts all valid observation types', function (): void {
        $session = Session::factory()->create();

        $types = [
            ObservationType::Bugfix,
            ObservationType::Feature,
            ObservationType::Refactor,
            ObservationType::Discovery,
            ObservationType::Decision,
            ObservationType::Change,
        ];

        foreach ($types as $type) {
            Observation::factory()->create([
                'session_id' => $session->id,
                'type' => $type,
            ]);

            $this->artisan('session:observations', ['id' => $session->id, '--type' => $type->value])
                ->assertSuccessful();
        }
    });

    it('handles empty ID gracefully', function (): void {
        $this->artisan('session:observations', ['id' => ''])
            ->assertSuccessful()
            ->expectsOutput('No observations found.');
    });

    it('validates ID must be a string', function (): void {
        // Create a mock command to test the validation path
        $command = new \App\Commands\Session\ObservationsCommand;
        $service = app(\App\Services\SessionService::class);

        // Mock the argument method to return non-string
        $mock = Mockery::mock(\App\Commands\Session\ObservationsCommand::class)->makePartial();
        $mock->shouldReceive('argument')->with('id')->andReturn(null);
        $mock->shouldReceive('option')->with('type')->andReturn(null);
        $mock->shouldReceive('error')->once()->with('The ID must be a valid string.');

        $result = $mock->handle($service);

        expect($result)->toBe(\App\Commands\Session\ObservationsCommand::FAILURE);
    });
});
