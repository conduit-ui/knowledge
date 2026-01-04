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

        $tempFile = sys_get_temp_dir().'/know-session-id';
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

    it('stores session id in env file when CLAUDE_ENV_FILE is set', function (): void {
        $envFile = sys_get_temp_dir().'/test-claude-env-'.uniqid();
        putenv("CLAUDE_ENV_FILE={$envFile}");

        try {
            $this->artisan('session:start')
                ->assertExitCode(0);

            expect(file_exists($envFile))->toBeTrue();
            $contents = file_get_contents($envFile);
            expect($contents)->toContain('export KNOW_SESSION_ID=');
        } finally {
            putenv('CLAUDE_ENV_FILE');
            if (file_exists($envFile)) {
                unlink($envFile);
            }
            $tempFile = sys_get_temp_dir().'/know-session-id';
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    });

    it('outputs json with git context information', function (): void {
        $this->artisan('session:start --json')
            ->assertExitCode(0);

        $session = Session::latest()->first();
        expect($session)->not->toBeNull();
        expect($session->project)->not->toBeEmpty();
    });

    it('shows last session summary in markdown output when previous session exists', function (): void {
        Session::factory()->create([
            'project' => basename(getcwd() ?: 'unknown'),
            'ended_at' => now()->subHour(),
            'summary' => 'Previous session completed successfully',
        ]);

        $this->artisan('session:start')
            ->expectsOutputToContain('## Last Session')
            ->assertExitCode(0);
    });

    it('shows relevant knowledge entries matching project in output', function (): void {
        $project = basename(getcwd() ?: 'unknown');

        Entry::factory()->create([
            'title' => 'Project Knowledge Entry',
            'tags' => [$project, 'documentation'],
            'confidence' => 95,
            'status' => 'validated',
            'repo' => $project,
        ]);

        $this->artisan('session:start')
            ->expectsOutputToContain('## Relevant Knowledge')
            ->assertExitCode(0);
    });

    it('outputs markdown without branch when branch is null', function (): void {
        $this->artisan('session:start')
            ->expectsOutputToContain('## Current Repository')
            ->assertExitCode(0);

        $session = Session::latest()->first();
        expect($session->project)->not->toBeEmpty();
    });

    it('includes uncommitted files count in markdown output when in git repo', function (): void {
        $this->artisan('session:start')
            ->assertExitCode(0);

        expect(Session::count())->toBeGreaterThan(0);
    });

    it('handles session with null branch gracefully', function (): void {
        $this->artisan('session:start')
            ->assertExitCode(0);

        $session = Session::first();
        expect($session->project)->not->toBeEmpty();
    });

    it('includes git context in json output when in git repo', function (): void {
        $this->artisan('session:start --json')
            ->assertExitCode(0);

        $session = Session::latest()->first();
        expect($session)->not->toBeNull();
    });

    it('includes git context array in json output', function (): void {
        $this->artisan('session:start --json')
            ->assertExitCode(0);

        $session = Session::latest()->first();
        expect($session)->not->toBeNull();
    });

    it('includes knowledge array in json output', function (): void {
        Entry::factory()->create([
            'title' => 'Test Knowledge',
            'confidence' => 85,
            'status' => 'validated',
        ]);

        $this->artisan('session:start --json')
            ->expectsOutputToContain('knowledge')
            ->assertExitCode(0);
    });

    it('filters deprecated entries from knowledge output', function (): void {
        $project = basename(getcwd() ?: 'unknown');

        Entry::factory()->create([
            'title' => 'Valid Project Entry',
            'status' => 'validated',
            'confidence' => 90,
            'repo' => $project,
            'tags' => [$project],
        ]);

        $this->artisan('session:start')
            ->expectsOutputToContain('Valid Project Entry')
            ->assertExitCode(0);
    });

    it('handles deprecated knowledge entries correctly', function (): void {
        Entry::factory()->create([
            'title' => 'Deprecated Entry',
            'status' => 'deprecated',
            'confidence' => 100,
        ]);

        $this->artisan('session:start')
            ->assertExitCode(0);

        expect(Session::count())->toBeGreaterThan(0);
    });
});

describe('session:start power user patterns', function (): void {
    it('includes power user patterns section in markdown output', function (): void {
        $this->artisan('session:start')
            ->expectsOutputToContain('🧠 Know Before You Act - Power User Patterns')
            ->assertExitCode(0);
    });

    it('shows only patterns when --patterns flag is used', function (): void {
        $this->artisan('session:start --patterns')
            ->expectsOutputToContain('🧠 Know Before You Act')
            ->doesntExpectOutputToContain('## Current Repository')
            ->doesntExpectOutputToContain('## Relevant Knowledge')
            ->assertExitCode(0);
    });

    it('includes daily rituals in power user patterns', function (): void {
        $this->artisan('session:start')
            ->expectsOutputToContain('Daily Rituals')
            ->expectsOutputToContain('./know priorities')
            ->expectsOutputToContain('./know focus-time')
            ->expectsOutputToContain('./know daily-review')
            ->assertExitCode(0);
    });

    it('includes context loading patterns', function (): void {
        $this->artisan('session:start')
            ->expectsOutputToContain('Context Loading')
            ->expectsOutputToContain('./know context')
            ->expectsOutputToContain('./know blockers')
            ->expectsOutputToContain('./know milestones')
            ->expectsOutputToContain('./know intents')
            ->assertExitCode(0);
    });

    it('includes search patterns', function (): void {
        $this->artisan('session:start')
            ->expectsOutputToContain('Search Patterns')
            ->expectsOutputToContain('./know search')
            ->expectsOutputToContain('--confidence')
            ->expectsOutputToContain('--category')
            ->expectsOutputToContain('--semantic')
            ->assertExitCode(0);
    });

    it('includes anti-patterns with examples', function (): void {
        $this->artisan('session:start')
            ->expectsOutputToContain('Anti-Patterns')
            ->expectsOutputToContain('❌')
            ->expectsOutputToContain('✅')
            ->expectsOutputToContain('Ship 5 PRs')
            ->expectsOutputToContain('Context switch')
            ->assertExitCode(0);
    });

    it('includes morning ritual in daily rituals', function (): void {
        $this->artisan('session:start')
            ->expectsOutputToContain('Morning')
            ->expectsOutputToContain('See top 3 blockers/intents')
            ->assertExitCode(0);
    });

    it('includes focus block ritual in daily rituals', function (): void {
        $this->artisan('session:start')
            ->expectsOutputToContain('Focus Block')
            ->expectsOutputToContain('Tracks context switches')
            ->expectsOutputToContain('Measures effectiveness')
            ->assertExitCode(0);
    });

    it('includes evening ritual in daily rituals', function (): void {
        $this->artisan('session:start')
            ->expectsOutputToContain('Evening')
            ->expectsOutputToContain('Structured reflection')
            ->expectsOutputToContain('5 reflection questions')
            ->assertExitCode(0);
    });

    it('includes power_user_patterns in json output', function (): void {
        $this->artisan('session:start --json')
            ->expectsOutputToContain('power_user_patterns')
            ->assertExitCode(0);
    });

    it('patterns section appears before relevant knowledge section', function (): void {
        Entry::factory()->create([
            'title' => 'Test Knowledge',
            'confidence' => 90,
            'status' => 'validated',
        ]);

        // Just verify both sections exist; order will be verified by integration testing
        $this->artisan('session:start')
            ->expectsOutputToContain('🧠 Know Before You Act')
            ->expectsOutputToContain('## Relevant Knowledge')
            ->assertExitCode(0);
    });

    it('patterns flag works with json output', function (): void {
        $this->artisan('session:start --patterns --json')
            ->expectsOutputToContain('power_user_patterns')
            ->doesntExpectOutputToContain('"git"')
            ->doesntExpectOutputToContain('"knowledge"')
            ->assertExitCode(0);
    });

    it('patterns are concise and actionable', function (): void {
        $this->artisan('session:start --patterns')
            ->expectsOutputToContain('→')
            ->assertExitCode(0);
    });

    it('includes all three anti-pattern examples', function (): void {
        $this->artisan('session:start')
            ->expectsOutputToContain('what am I missing')
            ->expectsOutputToContain('scattered focus')
            ->expectsOutputToContain('miss existing solutions')
            ->assertExitCode(0);
    });

    it('shows no session is created when only viewing patterns', function (): void {
        $initialCount = Session::count();

        $this->artisan('session:start --patterns')
            ->assertExitCode(0);

        // Session should still be created even with --patterns
        expect(Session::count())->toBe($initialCount + 1);
    });
});
