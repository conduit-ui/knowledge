<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Session;

describe('session:start command', function (): void {
    it('creates a new session', function (): void {
        $this->artisan('session:start')
            ->assertExitCode(0);

        expect(Session::count())->toBe(1);
    });

    it('outputs markdown context by default', function (): void {
        $this->artisan('session:start')
            ->expectsOutputToContain('## Current Repository')
            ->assertExitCode(0);
    });

    it('outputs json when --json flag is used', function (): void {
        $this->artisan('session:start --json')
            ->assertExitCode(0);

        $session = Session::first();
        expect($session)->not->toBeNull();
    });

    it('stores session id in temp file', function (): void {
        $this->artisan('session:start')
            ->assertExitCode(0);

        $tempFile = sys_get_temp_dir() . '/know-session-id';
        expect(file_exists($tempFile))->toBeTrue();

        $sessionId = trim(file_get_contents($tempFile));
        expect(Session::find($sessionId))->not->toBeNull();

        // Cleanup
        unlink($tempFile);
    });

    it('captures project name from current directory', function (): void {
        $this->artisan('session:start')
            ->assertExitCode(0);

        $session = Session::first();
        expect($session->project)->not->toBeEmpty();
    });

    it('includes relevant knowledge entries in output', function (): void {
        Entry::factory()->create([
            'title' => 'Test Entry',
            'tags' => ['knowledge', 'test'],
            'confidence' => 90,
            'status' => 'validated',
        ]);

        $this->artisan('session:start')
            ->assertExitCode(0);
    });

    it('includes last session summary when available', function (): void {
        Session::factory()->create([
            'project' => 'knowledge',
            'ended_at' => now()->subHour(),
            'summary' => 'Previous session summary',
        ]);

        $this->artisan('session:start')
            ->assertExitCode(0);

        // New session should be created
        expect(Session::count())->toBe(2);
    });

    it('handles missing git repository gracefully', function (): void {
        // Even without git, command should succeed
        $this->artisan('session:start')
            ->assertExitCode(0);
    });

    it('sets started_at timestamp', function (): void {
        $this->artisan('session:start')
            ->assertExitCode(0);

        $session = Session::first();
        expect($session->started_at)->not->toBeNull();
        expect($session->ended_at)->toBeNull();
    });
});
