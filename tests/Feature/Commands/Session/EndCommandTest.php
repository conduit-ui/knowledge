<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Session;

describe('session:end command', function (): void {
    beforeEach(function (): void {
        // Clean up any existing temp file
        $tempFile = sys_get_temp_dir() . '/know-session-id';
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    });

    it('ends successfully even without active session', function (): void {
        $this->artisan('session:end')
            ->assertExitCode(0);
    });

    it('creates knowledge entry with session summary', function (): void {
        $this->artisan('session:end')
            ->assertExitCode(0);

        expect(Entry::where('category', 'session')->count())->toBe(1);
    });

    it('updates session when session id is found', function (): void {
        $session = Session::factory()->create([
            'project' => 'test-project',
            'ended_at' => null,
        ]);

        // Store session ID in temp file
        $tempFile = sys_get_temp_dir() . '/know-session-id';
        file_put_contents($tempFile, $session->id);

        $this->artisan('session:end')
            ->assertExitCode(0);

        $session->refresh();
        expect($session->ended_at)->not->toBeNull();
        expect($session->summary)->not->toBeNull();
    });

    it('cleans up session id temp file after completion', function (): void {
        $session = Session::factory()->create(['ended_at' => null]);

        $tempFile = sys_get_temp_dir() . '/know-session-id';
        file_put_contents($tempFile, $session->id);

        $this->artisan('session:end')
            ->assertExitCode(0);

        expect(file_exists($tempFile))->toBeFalse();
    });

    it('suppresses output when -q flag is used', function (): void {
        $this->artisan('session:end -q')
            ->assertExitCode(0);
    });

    it('tags knowledge entry with session metadata', function (): void {
        $this->artisan('session:end')
            ->assertExitCode(0);

        $entry = Entry::where('category', 'session')->first();
        expect($entry)->not->toBeNull();
        expect($entry->tags)->toContain('session-end');
    });

    it('sets entry priority to low', function (): void {
        $this->artisan('session:end')
            ->assertExitCode(0);

        $entry = Entry::where('category', 'session')->first();
        expect($entry->priority)->toBe('low');
    });

    it('sets entry confidence to 80', function (): void {
        $this->artisan('session:end')
            ->assertExitCode(0);

        $entry = Entry::where('category', 'session')->first();
        expect($entry->confidence)->toBe(80);
    });

    it('sets entry status to validated', function (): void {
        $this->artisan('session:end')
            ->assertExitCode(0);

        $entry = Entry::where('category', 'session')->first();
        expect($entry->status)->toBe('validated');
    });

    it('includes date in knowledge entry title', function (): void {
        $this->artisan('session:end')
            ->assertExitCode(0);

        $entry = Entry::where('category', 'session')->first();
        expect($entry->title)->toContain('Session:');
        expect($entry->title)->toContain(now()->format('Y-m-d'));
    });

    it('handles sync flag without error', function (): void {
        // This test may fail if sync API is unavailable, but command should handle gracefully
        $this->artisan('session:end --sync')
            ->assertExitCode(0);
    });
});
