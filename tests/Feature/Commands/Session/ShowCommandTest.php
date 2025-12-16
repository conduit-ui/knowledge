<?php

declare(strict_types=1);

use App\Enums\ObservationType;
use App\Models\Observation;
use App\Models\Session;

describe('session:show command', function (): void {
    it('shows full details of a session', function (): void {
        $session = Session::factory()->create([
            'project' => 'test-project',
            'branch' => 'feature/test',
            'started_at' => now()->subHours(2),
            'ended_at' => now(),
            'summary' => 'This is a session summary',
        ]);

        $this->artisan('session:show', ['id' => $session->id])
            ->assertSuccessful()
            ->expectsOutput("ID: {$session->id}")
            ->expectsOutput("Project: {$session->project}")
            ->expectsOutput("Branch: {$session->branch}")
            ->expectsOutputToContain('Started At:')
            ->expectsOutputToContain('Ended At:')
            ->expectsOutputToContain('Duration:')
            ->expectsOutput("Summary: {$session->summary}")
            ->expectsOutputToContain('Created:')
            ->expectsOutputToContain('Updated:');
    });

    it('shows session with minimal fields', function (): void {
        $session = Session::factory()->create([
            'project' => 'minimal-project',
            'branch' => null,
            'ended_at' => null,
            'summary' => null,
        ]);

        $this->artisan('session:show', ['id' => $session->id])
            ->assertSuccessful()
            ->expectsOutput("ID: {$session->id}")
            ->expectsOutput("Project: {$session->project}")
            ->expectsOutput('Branch: N/A')
            ->expectsOutput('Status: Active');
    });

    it('shows active status when ended_at is null', function (): void {
        $session = Session::factory()->create(['ended_at' => null]);

        $this->artisan('session:show', ['id' => $session->id])
            ->assertSuccessful()
            ->expectsOutput('Status: Active');
    });

    it('shows duration when session is completed', function (): void {
        $session = Session::factory()->create([
            'started_at' => now()->subHours(2),
            'ended_at' => now(),
        ]);

        $this->artisan('session:show', ['id' => $session->id])
            ->assertSuccessful()
            ->expectsOutputToContain('Duration:');
    });

    it('shows observations count', function (): void {
        $session = Session::factory()->create();
        Observation::factory(5)->create(['session_id' => $session->id]);

        $this->artisan('session:show', ['id' => $session->id])
            ->assertSuccessful()
            ->expectsOutput('Observations: 5');
    });

    it('shows observations count as zero when none exist', function (): void {
        $session = Session::factory()->create();

        $this->artisan('session:show', ['id' => $session->id])
            ->assertSuccessful()
            ->expectsOutput('Observations: 0');
    });

    it('shows observations grouped by type', function (): void {
        $session = Session::factory()->create();

        Observation::factory(3)->create([
            'session_id' => $session->id,
            'type' => ObservationType::Feature,
        ]);

        Observation::factory(2)->create([
            'session_id' => $session->id,
            'type' => ObservationType::Bugfix,
        ]);

        Observation::factory()->create([
            'session_id' => $session->id,
            'type' => ObservationType::Discovery,
        ]);

        $this->artisan('session:show', ['id' => $session->id])
            ->assertSuccessful()
            ->expectsOutput('Observations: 6')
            ->expectsOutputToContain('feature: 3')
            ->expectsOutputToContain('bugfix: 2')
            ->expectsOutputToContain('discovery: 1');
    });

    it('shows error when session not found', function (): void {
        $this->artisan('session:show', ['id' => 'non-existent-id'])
            ->assertFailed()
            ->expectsOutput('Session not found.');
    });

    it('shows timestamps in correct format', function (): void {
        $session = Session::factory()->create([
            'started_at' => now(),
        ]);

        $expectedFormat = $session->started_at->format('Y-m-d H:i:s');

        $this->artisan('session:show', ['id' => $session->id])
            ->assertSuccessful()
            ->expectsOutput("Started At: {$expectedFormat}");
    });

    it('handles invalid ID gracefully', function (): void {
        $this->artisan('session:show', ['id' => ''])
            ->assertFailed();
    });

    it('validates ID must be a string', function (): void {
        // Create a mock command to test the validation path
        $command = new \App\Commands\Session\ShowCommand;
        $service = app(\App\Services\SessionService::class);

        // Use reflection to test the validation logic
        $reflection = new ReflectionMethod($command, 'handle');

        // Mock the argument method to return non-string
        $mock = Mockery::mock(\App\Commands\Session\ShowCommand::class)->makePartial();
        $mock->shouldReceive('argument')->with('id')->andReturn(null);
        $mock->shouldReceive('error')->once()->with('The ID must be a valid string.');

        $result = $mock->handle($service);

        expect($result)->toBe(\App\Commands\Session\ShowCommand::FAILURE);
    });
});
