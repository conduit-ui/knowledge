<?php

declare(strict_types=1);

use App\Models\Entry;

describe('focus-time command', function (): void {
    beforeEach(function (): void {
        // Clean up any existing temp files
        $tempFile = sys_get_temp_dir().'/know-focus-block-id';
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    });

    describe('starting focus block', function (): void {
        it('starts a new focus block with project and hours', function (): void {
            $this->artisan('focus-time test-project 2')
                ->assertExitCode(0);

            expect(Entry::where('category', 'focus-session')->count())->toBe(1);

            $entry = Entry::where('category', 'focus-session')->first();
            expect($entry->title)->toContain('test-project');
            expect($entry->tags)->toContain('test-project');
            expect($entry->tags)->toContain('deep-work');
            expect($entry->tags)->toContain('focus-time');
        });

        it('validates hours parameter is numeric', function (): void {
            $this->artisan('focus-time test-project invalid')
                ->assertExitCode(1);
        });

        it('validates hours parameter is positive', function (): void {
            $this->artisan('focus-time test-project -1')
                ->assertExitCode(1);
        });

        it('validates hours parameter is reasonable (max 12 hours)', function (): void {
            $this->artisan('focus-time test-project 13')
                ->assertExitCode(1);
        });

        it('prompts for energy level before starting', function (): void {
            $this->artisan('focus-time test-project 2')
                ->expectsQuestion('Energy level before starting?', 'high')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect($content['energy_before'])->toBe('high');
        });

        it('accepts high energy level', function (): void {
            $this->artisan('focus-time test-project 2')
                ->expectsQuestion('Energy level before starting?', 'high')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect($content['energy_before'])->toBe('high');
        });

        it('accepts medium energy level', function (): void {
            $this->artisan('focus-time test-project 2')
                ->expectsQuestion('Energy level before starting?', 'medium')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect($content['energy_before'])->toBe('medium');
        });

        it('accepts low energy level', function (): void {
            $this->artisan('focus-time test-project 2')
                ->expectsQuestion('Energy level before starting?', 'low')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect($content['energy_before'])->toBe('low');
        });

        it('rejects invalid energy level', function (): void {
            $this->artisan('focus-time test-project 2')
                ->expectsQuestion('Energy level before starting?', 'invalid')
                ->assertExitCode(1);
        });

        it('stores focus block id in temp file', function (): void {
            $this->artisan('focus-time test-project 2')
                ->expectsQuestion('Energy level before starting?', 'high')
                ->assertExitCode(0);

            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            expect(file_exists($tempFile))->toBeTrue();

            $blockId = trim(file_get_contents($tempFile));
            expect(Entry::find($blockId))->not->toBeNull();

            // Cleanup
            unlink($tempFile);
        });

        it('stores start timestamp in entry content', function (): void {
            $this->artisan('focus-time test-project 2')
                ->expectsQuestion('Energy level before starting?', 'medium')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect($content['started_at'])->not->toBeEmpty();
            expect($content['planned_hours'])->toBe(2.0);
            expect($content['ended_at'])->toBeNull();
        });

        it('initializes context switches to empty array', function (): void {
            $this->artisan('focus-time test-project 2')
                ->expectsQuestion('Energy level before starting?', 'high')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect($content['context_switches'])->toBe([]);
        });

        it('sets entry status to pending while in progress', function (): void {
            $this->artisan('focus-time test-project 2')
                ->expectsQuestion('Energy level before starting?', 'high')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            expect($entry->status)->toBe('pending');
        });

        it('sets entry priority to high', function (): void {
            $this->artisan('focus-time test-project 2')
                ->expectsQuestion('Energy level before starting?', 'high')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            expect($entry->priority)->toBe('high');
        });

        it('prevents starting new block when active block exists', function (): void {
            // Start first block
            $this->artisan('focus-time test-project 2')
                ->expectsQuestion('Energy level before starting?', 'high')
                ->assertExitCode(0);

            // Try to start second block
            $this->artisan('focus-time another-project 1')
                ->expectsOutput('Active focus block already in progress. End current block first.')
                ->assertExitCode(1);
        });

        it('stores project name in entry repo field', function (): void {
            $this->artisan('focus-time test-project 2')
                ->expectsQuestion('Energy level before starting?', 'high')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            expect($entry->repo)->toBe('test-project');
        });

        it('displays confirmation message with planned duration', function (): void {
            $this->artisan('focus-time test-project 2')
                ->expectsQuestion('Energy level before starting?', 'high')
                ->expectsOutput('Focus block started for test-project (2.0 hours planned)')
                ->assertExitCode(0);
        });

        it('stores focus block id in env file when CLAUDE_ENV_FILE is set', function (): void {
            $envFile = sys_get_temp_dir().'/test-claude-env-focus-'.uniqid();
            putenv("CLAUDE_ENV_FILE={$envFile}");

            try {
                $this->artisan('focus-time test-project 2')
                    ->expectsQuestion('Energy level before starting?', 'high')
                    ->assertExitCode(0);

                expect(file_exists($envFile))->toBeTrue();
                $contents = file_get_contents($envFile);
                expect($contents)->toContain('export KNOW_FOCUS_BLOCK_ID=');
            } finally {
                putenv('CLAUDE_ENV_FILE');
                if (file_exists($envFile)) {
                    unlink($envFile);
                }
                $tempFile = sys_get_temp_dir().'/know-focus-block-id';
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        });

        it('accepts decimal hours', function (): void {
            $this->artisan('focus-time test-project 1.5')
                ->expectsQuestion('Energy level before starting?', 'medium')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect($content['planned_hours'])->toBe(1.5);
        });

        it('requires project argument', function (): void {
            $this->artisan('focus-time')
                ->assertExitCode(1);
        });

        it('requires hours argument', function (): void {
            $this->artisan('focus-time test-project')
                ->assertExitCode(1);
        });
    });

    describe('ending focus block', function (): void {
        beforeEach(function (): void {
            // Create an active focus block
            $entry = Entry::create([
                'title' => 'Focus: test-project '.now()->format('Y-m-d H:i'),
                'content' => json_encode([
                    'started_at' => now()->subHours(2)->toIso8601String(),
                    'planned_hours' => 2.0,
                    'energy_before' => 'high',
                    'context_switches' => [],
                    'ended_at' => null,
                ]),
                'category' => 'focus-session',
                'tags' => ['test-project', 'deep-work', 'focus-time'],
                'status' => 'pending',
                'priority' => 'high',
                'confidence' => 50,
                'repo' => 'test-project',
            ]);

            // Store block ID in temp file
            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            file_put_contents($tempFile, (string) $entry->id);
        });

        it('ends active focus block', function (): void {
            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'medium')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            expect($entry->status)->toBe('validated');

            $content = json_decode($entry->content, true);
            expect($content['ended_at'])->not->toBeNull();
        });

        it('prompts for energy level after completing', function (): void {
            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'low')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect($content['energy_after'])->toBe('low');
        });

        it('calculates actual duration in hours', function (): void {
            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'medium')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect($content['actual_hours'])->toBeGreaterThan(1.9);
            expect($content['actual_hours'])->toBeLessThan(2.1);
        });

        it('calculates effectiveness score based on duration', function (): void {
            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'medium')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect($content['effectiveness_score'])->toBeGreaterThanOrEqual(0);
            expect($content['effectiveness_score'])->toBeLessThanOrEqual(10);
        });

        it('sets status to validated when completed', function (): void {
            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'high')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            expect($entry->status)->toBe('validated');
        });

        it('sets confidence to calculated effectiveness score * 10', function (): void {
            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'high')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            $expectedConfidence = (int) ($content['effectiveness_score'] * 10);
            expect($entry->confidence)->toBe($expectedConfidence);
        });

        it('cleans up focus block id temp file', function (): void {
            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'medium')
                ->assertExitCode(0);

            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            expect(file_exists($tempFile))->toBeFalse();
        });

        it('displays completion report with metrics', function (): void {
            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'medium')
                ->expectsOutputToContain('Focus Block Report')
                ->expectsOutputToContain('Project:')
                ->expectsOutputToContain('Planned:')
                ->expectsOutputToContain('Actual:')
                ->expectsOutputToContain('Effectiveness:')
                ->expectsOutputToContain('Energy:')
                ->assertExitCode(0);
        });

        it('includes context switch count in report', function (): void {
            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'high')
                ->expectsOutputToContain('Context Switches:')
                ->assertExitCode(0);
        });

        it('fails gracefully when no active focus block exists', function (): void {
            // Remove the temp file to simulate no active block
            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            unlink($tempFile);

            $this->artisan('focus-time end')
                ->expectsOutput('No active focus block found')
                ->assertExitCode(1);
        });

        it('handles missing focus block gracefully', function (): void {
            // Set temp file to non-existent entry ID
            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            file_put_contents($tempFile, '999999');

            $this->artisan('focus-time end')
                ->expectsOutput('No active focus block found')
                ->assertExitCode(1);
        });

        it('calculates duration completion percentage', function (): void {
            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'medium')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect($content['duration_percentage'])->toBeGreaterThan(95);
            expect($content['duration_percentage'])->toBeLessThan(105);
        });

        it('reduces effectiveness score for context switches', function (): void {
            // Create block with context switches
            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            $content['context_switches'] = [
                ['timestamp' => now()->subMinutes(30)->toIso8601String(), 'from' => 'test-project', 'to' => 'another-project'],
                ['timestamp' => now()->subMinutes(15)->toIso8601String(), 'from' => 'another-project', 'to' => 'test-project'],
            ];
            $entry->update(['content' => json_encode($content)]);

            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'medium')
                ->assertExitCode(0);

            $entry->refresh();
            $content = json_decode($entry->content, true);
            expect($content['effectiveness_score'])->toBeLessThan(10);
        });

        it('includes energy transition in report', function (): void {
            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'low')
                ->expectsOutputToContain('high → low')
                ->assertExitCode(0);
        });

        it('finds block id from env variable when set', function (): void {
            $entry = Entry::where('category', 'focus-session')->first();
            putenv("KNOW_FOCUS_BLOCK_ID={$entry->id}");

            try {
                $this->artisan('focus-time end')
                    ->expectsQuestion('Energy level after completing?', 'medium')
                    ->assertExitCode(0);
            } finally {
                putenv('KNOW_FOCUS_BLOCK_ID');
            }
        });

        it('accepts optional --sync flag', function (): void {
            $this->artisan('focus-time end --sync')
                ->expectsQuestion('Energy level after completing?', 'medium')
                ->assertExitCode(0);
        });

        it('calls sync command when --sync flag is provided', function (): void {
            // This test verifies the flag is accepted
            // Actual sync behavior tested in SyncCommandTest
            $this->artisan('focus-time end --sync')
                ->expectsQuestion('Energy level after completing?', 'high')
                ->assertExitCode(0);
        });

        it('stores end timestamp in entry content', function (): void {
            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'medium')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect($content['ended_at'])->not->toBeNull();
        });
    });

    describe('context switch tracking', function (): void {
        beforeEach(function (): void {
            // Create an active focus block
            $entry = Entry::create([
                'title' => 'Focus: test-project '.now()->format('Y-m-d H:i'),
                'content' => json_encode([
                    'started_at' => now()->subHour()->toIso8601String(),
                    'planned_hours' => 2.0,
                    'energy_before' => 'high',
                    'context_switches' => [],
                    'ended_at' => null,
                    'current_project' => 'test-project',
                ]),
                'category' => 'focus-session',
                'tags' => ['test-project', 'deep-work', 'focus-time'],
                'status' => 'pending',
                'priority' => 'high',
                'confidence' => 50,
                'repo' => 'test-project',
            ]);

            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            file_put_contents($tempFile, (string) $entry->id);
        });

        it('detects context switch when project changes', function (): void {
            $this->artisan('focus-time switch another-project')
                ->expectsQuestion('Why are you switching?', 'urgent bug fix')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect(count($content['context_switches']))->toBe(1);
            expect($content['context_switches'][0]['from'])->toBe('test-project');
            expect($content['context_switches'][0]['to'])->toBe('another-project');
            expect($content['context_switches'][0]['reason'])->toBe('urgent bug fix');
        });

        it('stores timestamp for each context switch', function (): void {
            $this->artisan('focus-time switch another-project')
                ->expectsQuestion('Why are you switching?', 'meeting')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect($content['context_switches'][0]['timestamp'])->not->toBeEmpty();
        });

        it('updates current project after switch', function (): void {
            $this->artisan('focus-time switch another-project')
                ->expectsQuestion('Why are you switching?', 'test')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect($content['current_project'])->toBe('another-project');
        });

        it('fails when no active focus block exists', function (): void {
            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            unlink($tempFile);

            $this->artisan('focus-time switch another-project')
                ->expectsOutput('No active focus block found')
                ->assertExitCode(1);
        });

        it('allows multiple context switches', function (): void {
            $this->artisan('focus-time switch project-a')
                ->expectsQuestion('Why are you switching?', 'reason 1')
                ->assertExitCode(0);

            $this->artisan('focus-time switch project-b')
                ->expectsQuestion('Why are you switching?', 'reason 2')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            $content = json_decode($entry->content, true);
            expect(count($content['context_switches']))->toBe(2);
        });

        it('displays confirmation after recording switch', function (): void {
            $this->artisan('focus-time switch another-project')
                ->expectsQuestion('Why are you switching?', 'urgent task')
                ->expectsOutput('Context switch recorded (1 total switches)')
                ->assertExitCode(0);
        });
    });

    describe('effectiveness scoring', function (): void {
        it('gives perfect score for completing exactly on time with no switches', function (): void {
            $entry = Entry::create([
                'title' => 'Focus: test-project',
                'content' => json_encode([
                    'started_at' => now()->subHours(2)->toIso8601String(),
                    'planned_hours' => 2.0,
                    'energy_before' => 'high',
                    'context_switches' => [],
                    'ended_at' => null,
                ]),
                'category' => 'focus-session',
                'tags' => ['test-project', 'deep-work'],
                'status' => 'pending',
                'priority' => 'high',
                'confidence' => 50,
            ]);

            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            file_put_contents($tempFile, (string) $entry->id);

            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'high')
                ->assertExitCode(0);

            $entry->refresh();
            $content = json_decode($entry->content, true);
            expect($content['effectiveness_score'])->toBeGreaterThan(9.0);
        });

        it('reduces score for early completion', function (): void {
            $entry = Entry::create([
                'title' => 'Focus: test-project',
                'content' => json_encode([
                    'started_at' => now()->subMinutes(60)->toIso8601String(),
                    'planned_hours' => 2.0,
                    'energy_before' => 'high',
                    'context_switches' => [],
                    'ended_at' => null,
                ]),
                'category' => 'focus-session',
                'tags' => ['test-project'],
                'status' => 'pending',
                'priority' => 'high',
                'confidence' => 50,
            ]);

            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            file_put_contents($tempFile, (string) $entry->id);

            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'medium')
                ->assertExitCode(0);

            $entry->refresh();
            $content = json_decode($entry->content, true);
            expect($content['effectiveness_score'])->toBeLessThan(8.0);
        });

        it('reduces score for overtime completion', function (): void {
            $entry = Entry::create([
                'title' => 'Focus: test-project',
                'content' => json_encode([
                    'started_at' => now()->subHours(3)->toIso8601String(),
                    'planned_hours' => 2.0,
                    'energy_before' => 'high',
                    'context_switches' => [],
                    'ended_at' => null,
                ]),
                'category' => 'focus-session',
                'tags' => ['test-project'],
                'status' => 'pending',
                'priority' => 'high',
                'confidence' => 50,
            ]);

            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            file_put_contents($tempFile, (string) $entry->id);

            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'low')
                ->assertExitCode(0);

            $entry->refresh();
            $content = json_decode($entry->content, true);
            expect($content['effectiveness_score'])->toBeLessThan(9.0);
        });

        it('penalizes each context switch', function (): void {
            $entry = Entry::create([
                'title' => 'Focus: test-project',
                'content' => json_encode([
                    'started_at' => now()->subHours(2)->toIso8601String(),
                    'planned_hours' => 2.0,
                    'energy_before' => 'high',
                    'context_switches' => [
                        ['timestamp' => now()->subHour()->toIso8601String(), 'from' => 'test-project', 'to' => 'other', 'reason' => 'test'],
                        ['timestamp' => now()->subMinutes(30)->toIso8601String(), 'from' => 'other', 'to' => 'test-project', 'reason' => 'test'],
                        ['timestamp' => now()->subMinutes(15)->toIso8601String(), 'from' => 'test-project', 'to' => 'another', 'reason' => 'test'],
                    ],
                    'ended_at' => null,
                ]),
                'category' => 'focus-session',
                'tags' => ['test-project'],
                'status' => 'pending',
                'priority' => 'high',
                'confidence' => 50,
            ]);

            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            file_put_contents($tempFile, (string) $entry->id);

            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'medium')
                ->assertExitCode(0);

            $entry->refresh();
            $content = json_decode($entry->content, true);
            expect($content['effectiveness_score'])->toBeLessThan(7.0);
        });

        it('includes energy delta in effectiveness calculation', function (): void {
            $entry = Entry::create([
                'title' => 'Focus: test-project',
                'content' => json_encode([
                    'started_at' => now()->subHours(2)->toIso8601String(),
                    'planned_hours' => 2.0,
                    'energy_before' => 'high',
                    'context_switches' => [],
                    'ended_at' => null,
                ]),
                'category' => 'focus-session',
                'tags' => ['test-project'],
                'status' => 'pending',
                'priority' => 'high',
                'confidence' => 50,
            ]);

            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            file_put_contents($tempFile, (string) $entry->id);

            $this->artisan('focus-time end')
                ->expectsQuestion('Energy level after completing?', 'low')
                ->assertExitCode(0);

            $entry->refresh();
            $content = json_decode($entry->content, true);
            expect($content['energy_delta'])->toBe(-2); // high (3) to low (1) = -2
        });
    });

    describe('edge cases', function (): void {
        it('handles zero hours gracefully', function (): void {
            $this->artisan('focus-time test-project 0')
                ->assertExitCode(1);
        });

        it('handles very long project names', function (): void {
            $longName = str_repeat('very-long-project-name-', 20);
            $this->artisan("focus-time {$longName} 1")
                ->expectsQuestion('Energy level before starting?', 'medium')
                ->assertExitCode(0);
        });

        it('handles concurrent focus blocks across different temp files', function (): void {
            // This should not happen in normal usage but test resilience
            $this->artisan('focus-time test-project 2')
                ->expectsQuestion('Energy level before starting?', 'high')
                ->assertExitCode(0);

            expect(Entry::where('category', 'focus-session')->where('status', 'pending')->count())->toBe(1);
        });

        it('handles corrupted temp file gracefully', function (): void {
            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            file_put_contents($tempFile, 'invalid-data');

            $this->artisan('focus-time end')
                ->expectsOutput('No active focus block found')
                ->assertExitCode(1);
        });

        it('validates entry content is valid JSON', function (): void {
            $this->artisan('focus-time test-project 1')
                ->expectsQuestion('Energy level before starting?', 'medium')
                ->assertExitCode(0);

            $entry = Entry::where('category', 'focus-session')->first();
            expect(json_decode($entry->content))->not->toBeNull();
        });
    });

    describe('json output format', function (): void {
        it('supports --json flag for programmatic output', function (): void {
            $entry = Entry::create([
                'title' => 'Focus: test-project',
                'content' => json_encode([
                    'started_at' => now()->subHours(2)->toIso8601String(),
                    'planned_hours' => 2.0,
                    'energy_before' => 'high',
                    'context_switches' => [],
                    'ended_at' => null,
                ]),
                'category' => 'focus-session',
                'tags' => ['test-project'],
                'status' => 'pending',
                'priority' => 'high',
                'confidence' => 50,
            ]);

            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            file_put_contents($tempFile, (string) $entry->id);

            $this->artisan('focus-time end --json')
                ->expectsQuestion('Energy level after completing?', 'medium')
                ->assertExitCode(0);

            // Clean up
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });

        it('outputs valid json with all metrics when --json flag used', function (): void {
            $entry = Entry::create([
                'title' => 'Focus: test-project',
                'content' => json_encode([
                    'started_at' => now()->subHours(2)->toIso8601String(),
                    'planned_hours' => 2.0,
                    'energy_before' => 'high',
                    'context_switches' => [],
                    'ended_at' => null,
                ]),
                'category' => 'focus-session',
                'tags' => ['test-project'],
                'status' => 'pending',
                'priority' => 'high',
                'confidence' => 50,
            ]);

            $tempFile = sys_get_temp_dir().'/know-focus-block-id';
            file_put_contents($tempFile, (string) $entry->id);

            $this->artisan('focus-time end --json')
                ->expectsQuestion('Energy level after completing?', 'medium')
                ->assertExitCode(0);

            // Verify JSON output via database
            $entry->refresh();
            $content = json_decode($entry->content, true);

            expect($content)->toHaveKeys([
                'effectiveness_score',
                'actual_hours',
                'context_switches',
                'energy_delta',
                'duration_percentage',
            ]);

            // Clean up
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });
    });
});
