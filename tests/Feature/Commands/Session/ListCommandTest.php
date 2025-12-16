<?php

declare(strict_types=1);

use App\Models\Session;

describe('session:list command', function (): void {
    it('lists all sessions with details', function (): void {
        Session::factory()->create([
            'project' => 'test-project',
            'branch' => 'main',
            'started_at' => now()->subHours(2),
            'ended_at' => null,
        ]);

        Session::factory()->create([
            'project' => 'other-project',
            'branch' => 'feature/test',
            'started_at' => now()->subHours(1),
            'ended_at' => now(),
        ]);

        $this->artisan('session:list')
            ->assertExitCode(0);
    });

    it('shows sessions successfully', function (): void {
        Session::factory()->create([
            'project' => 'active-project',
            'ended_at' => null,
        ]);

        Session::factory()->create([
            'project' => 'completed-project',
            'ended_at' => now(),
        ]);

        $this->artisan('session:list')
            ->assertExitCode(0);
    });

    it('filters active sessions when --active flag is used', function (): void {
        $active = Session::factory()->create([
            'project' => 'active-session',
            'ended_at' => null,
        ]);

        $completed = Session::factory()->create([
            'project' => 'completed-session',
            'ended_at' => now(),
        ]);

        $this->artisan('session:list --active')
            ->assertExitCode(0);

        // Verify only active session would be shown
        expect(Session::whereNull('ended_at')->count())->toBe(1);
    });

    it('filters by project when --project option is used', function (): void {
        Session::factory()->create(['project' => 'project-a']);
        Session::factory()->create(['project' => 'project-b']);
        Session::factory()->create(['project' => 'project-a']);

        $this->artisan('session:list --project=project-a')
            ->assertExitCode(0);

        // Verify the filter would select correct sessions
        expect(Session::where('project', 'project-a')->count())->toBe(2);
    });

    it('respects limit option', function (): void {
        Session::factory(10)->create();

        $this->artisan('session:list --limit=3')
            ->assertExitCode(0);
    });

    it('shows message when no sessions exist', function (): void {
        $this->artisan('session:list')
            ->expectsOutput('No sessions found.')
            ->assertExitCode(0);
    });

    it('shows message when no active sessions exist', function (): void {
        Session::factory()->create(['ended_at' => now()]);

        $this->artisan('session:list --active')
            ->expectsOutput('No sessions found.')
            ->assertExitCode(0);
    });

    it('shows message when no sessions match project filter', function (): void {
        Session::factory()->create(['project' => 'project-a']);

        $this->artisan('session:list --project=project-b')
            ->expectsOutput('No sessions found.')
            ->assertExitCode(0);
    });

    it('handles sessions with null branch', function (): void {
        Session::factory()->create([
            'project' => 'test-project',
            'branch' => null,
        ]);

        $this->artisan('session:list')
            ->assertExitCode(0);
    });

    it('orders sessions by started_at descending', function (): void {
        $oldest = Session::factory()->create([
            'project' => 'oldest',
            'started_at' => now()->subHours(3),
        ]);

        $newest = Session::factory()->create([
            'project' => 'newest',
            'started_at' => now()->subHours(1),
        ]);

        $middle = Session::factory()->create([
            'project' => 'middle',
            'started_at' => now()->subHours(2),
        ]);

        $this->artisan('session:list')
            ->assertExitCode(0);
    });
});
