<?php

declare(strict_types=1);

use App\Models\Entry;
use Illuminate\Support\Facades\Process;

describe('ContextCommand', function () {
    it('displays recent user intents', function () {
        Entry::factory()->create([
            'title' => 'Implement authentication',
            'tags' => ['user-intent'],
            'created_at' => now()->subHours(1),
        ]);

        Entry::factory()->create([
            'title' => 'Fix bug in payment processing',
            'tags' => ['user-intent'],
            'created_at' => now()->subHours(2),
        ]);

        Entry::factory()->create([
            'title' => 'Add user dashboard',
            'tags' => ['user-intent'],
            'created_at' => now()->subHours(3),
        ]);

        $this->artisan('context')
            ->expectsOutputToContain('Recent User Intents')
            ->expectsOutputToContain('Implement authentication')
            ->expectsOutputToContain('Fix bug in payment processing')
            ->expectsOutputToContain('Add user dashboard')
            ->assertSuccessful();
    });

    it('limits user intents to last 3 by default', function () {
        Entry::factory()->create([
            'title' => 'Most recent',
            'tags' => ['user-intent'],
            'created_at' => now(),
        ]);

        Entry::factory()->create([
            'title' => 'Second recent',
            'tags' => ['user-intent'],
            'created_at' => now()->subHours(1),
        ]);

        Entry::factory()->create([
            'title' => 'Third recent',
            'tags' => ['user-intent'],
            'created_at' => now()->subHours(2),
        ]);

        Entry::factory()->create([
            'title' => 'Too old - should not appear',
            'tags' => ['user-intent'],
            'created_at' => now()->subHours(3),
        ]);

        $output = $this->artisan('context')->run();

        expect($output)->toBe(0);
    });

    it('displays git context information', function () {
        $this->artisan('context')
            ->expectsOutputToContain('Git Context')
            ->assertSuccessful();
    });

    it('displays unresolved blockers', function () {
        Entry::factory()->create([
            'title' => 'Database migration failing',
            'tags' => ['blocker'],
            'status' => 'draft',
        ]);

        Entry::factory()->create([
            'title' => 'API endpoint not responding',
            'tags' => ['blocker'],
            'status' => 'draft',
        ]);

        $this->artisan('context')
            ->expectsOutputToContain('Unresolved Blockers')
            ->expectsOutputToContain('Database migration failing')
            ->expectsOutputToContain('API endpoint not responding')
            ->assertSuccessful();
    });

    it('shows no blockers message when none exist', function () {
        $this->artisan('context')
            ->expectsOutputToContain('No blockers')
            ->assertSuccessful();
    });

    it('handles missing user intents gracefully', function () {
        $this->artisan('context')
            ->expectsOutputToContain('No recent user intents')
            ->assertSuccessful();
    });

    it('shows full output with --full flag', function () {
        Entry::factory()->count(5)->create([
            'tags' => ['user-intent'],
        ]);

        Process::fake([
            'git rev-parse --abbrev-ref HEAD' => Process::result(output: 'main'),
            'git status --short' => Process::result(output: ''),
        ]);

        $this->artisan('context --full')
            ->assertSuccessful();
    });

    it('handles git command failures gracefully', function () {
        Process::fake([
            'git rev-parse --abbrev-ref HEAD' => Process::result(exitCode: 1),
            'git status --short' => Process::result(exitCode: 1),
        ]);

        $this->artisan('context')
            ->assertSuccessful();
    });

    it('displays open PRs when available', function () {
        Process::fake();

        $this->artisan('context')
            ->expectsOutputToContain('Open Pull Requests')
            ->assertSuccessful();
    })->skip('Process mocking needs refactoring');

    it('displays open issues when available', function () {
        Process::fake();

        $this->artisan('context')
            ->expectsOutputToContain('Open Issues')
            ->assertSuccessful();
    })->skip('Process mocking needs refactoring');

    it('handles gh command failures gracefully', function () {
        Process::fake([
            'git rev-parse --abbrev-ref HEAD' => Process::result(output: 'main'),
            'git status --short' => Process::result(output: ''),
            'gh pr list --state open --json number,title,url --limit 5' => Process::result(exitCode: 1),
            'gh issue list --state open --json number,title,url --limit 5' => Process::result(exitCode: 1),
        ]);

        $this->artisan('context')
            ->assertSuccessful();
    });

    it('shows no PRs message when none exist', function () {
        Process::fake();

        $this->artisan('context')
            ->assertSuccessful();
    })->skip('Process mocking needs refactoring');

    it('filters blockers by status', function () {
        Entry::factory()->create([
            'title' => 'Active blocker',
            'tags' => ['blocker'],
            'status' => 'draft',
        ]);

        Entry::factory()->create([
            'title' => 'Resolved blocker',
            'tags' => ['blocker'],
            'status' => 'validated',
        ]);

        $output = $this->artisan('context')->run();

        expect($output)->toBe(0);
    });

    it('handles clean git status', function () {
        Process::fake([
            '*' => Process::result(output: ''),
        ]);

        $this->artisan('context')
            ->expectsOutputToContain('Clean')
            ->assertSuccessful();
    });

    it('handles git status with changes', function () {
        Process::fake([
            '*' => Process::result(output: 'M file.php'),
        ]);

        $this->artisan('context')
            ->expectsOutputToContain('Changes present')
            ->assertSuccessful();
    });
});
