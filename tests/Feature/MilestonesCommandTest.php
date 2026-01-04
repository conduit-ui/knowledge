<?php

declare(strict_types=1);

use App\Models\Entry;
use Illuminate\Support\Facades\Process;

describe('MilestonesCommand', function () {
    // Basic Milestone Detection Tests
    it('shows milestones from entries with checkmark emoji', function () {
        Entry::factory()->create([
            'title' => 'Milestone Entry',
            'content' => '## Milestones\n- ✅ Completed feature X',
            'status' => 'validated',
            'created_at' => now()->subDays(2),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('Milestone Entry')
            ->assertSuccessful();
    });

    it('shows milestones from entries with milestone tag', function () {
        Entry::factory()->create([
            'title' => 'Tagged Milestone',
            'tags' => ['milestone', 'completed'],
            'status' => 'validated',
            'created_at' => now()->subDays(3),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('Tagged Milestone')
            ->assertSuccessful();
    });

    it('shows milestones from entries with accomplished tag', function () {
        Entry::factory()->create([
            'title' => 'Accomplished Work',
            'tags' => ['accomplished'],
            'status' => 'validated',
            'created_at' => now()->subDay(),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('Accomplished Work')
            ->assertSuccessful();
    });

    it('shows milestones from entries with ## Milestones section', function () {
        Entry::factory()->create([
            'title' => 'Entry with Milestones Section',
            'content' => "## Milestones\n- Deployed to production\n- 100% test coverage",
            'status' => 'validated',
            'created_at' => now()->subDay(),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('Entry with Milestones Section')
            ->assertSuccessful();
    });

    it('only shows validated entries', function () {
        Entry::factory()->create([
            'title' => 'Validated Milestone',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now()->subDays(2),
        ]);

        Entry::factory()->create([
            'title' => 'Draft Milestone',
            'tags' => ['milestone'],
            'status' => 'draft',
            'created_at' => now()->subDay(),
        ]);

        Entry::factory()->create([
            'title' => 'Deprecated Milestone',
            'tags' => ['milestone'],
            'status' => 'deprecated',
            'created_at' => now()->subDay(),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('Validated Milestone')
            ->doesntExpectOutputToContain('Draft Milestone')
            ->doesntExpectOutputToContain('Deprecated Milestone')
            ->assertSuccessful();
    });

    // Date Filtering Tests
    it('respects --since flag to filter by days', function () {
        Entry::factory()->create([
            'title' => 'Recent Milestone',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now()->subDays(3),
        ]);

        Entry::factory()->create([
            'title' => 'Old Milestone',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now()->subDays(30),
        ]);

        Process::fake();

        $this->artisan('milestones --since=7')
            ->expectsOutputToContain('Recent Milestone')
            ->doesntExpectOutputToContain('Old Milestone')
            ->assertSuccessful();
    });

    it('defaults to 7 days when --since not provided', function () {
        Entry::factory()->create([
            'title' => 'Within 7 Days',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now()->subDays(5),
        ]);

        Entry::factory()->create([
            'title' => 'Beyond 7 Days',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now()->subDays(10),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('Within 7 Days')
            ->doesntExpectOutputToContain('Beyond 7 Days')
            ->assertSuccessful();
    });

    it('accepts custom --since value for longer lookback', function () {
        Entry::factory()->create([
            'title' => 'Within 30 Days',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now()->subDays(25),
        ]);

        Entry::factory()->create([
            'title' => 'Beyond 30 Days',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now()->subDays(35),
        ]);

        Process::fake();

        $this->artisan('milestones --since=30')
            ->expectsOutputToContain('Within 30 Days')
            ->doesntExpectOutputToContain('Beyond 30 Days')
            ->assertSuccessful();
    });

    // Project Filtering Tests
    it('filters milestones by project tag', function () {
        Entry::factory()->create([
            'title' => 'Knowledge Project Milestone',
            'tags' => ['milestone', 'knowledge'],
            'status' => 'validated',
            'created_at' => now()->subDay(),
        ]);

        Entry::factory()->create([
            'title' => 'Other Project Milestone',
            'tags' => ['milestone', 'other-project'],
            'status' => 'validated',
            'created_at' => now()->subDay(),
        ]);

        Process::fake();

        $this->artisan('milestones --project=knowledge')
            ->expectsOutputToContain('Knowledge Project Milestone')
            ->doesntExpectOutputToContain('Other Project Milestone')
            ->assertSuccessful();
    });

    it('filters milestones by module field', function () {
        Entry::factory()->create([
            'title' => 'Module Based Milestone',
            'tags' => ['milestone'],
            'module' => 'knowledge',
            'status' => 'validated',
            'created_at' => now()->subDay(),
        ]);

        Entry::factory()->create([
            'title' => 'Other Module Milestone',
            'tags' => ['milestone'],
            'module' => 'other',
            'status' => 'validated',
            'created_at' => now()->subDay(),
        ]);

        Process::fake();

        $this->artisan('milestones --project=knowledge')
            ->expectsOutputToContain('Module Based Milestone')
            ->doesntExpectOutputToContain('Other Module Milestone')
            ->assertSuccessful();
    });

    it('combines --since and --project flags', function () {
        Entry::factory()->create([
            'title' => 'Recent Knowledge Milestone',
            'tags' => ['milestone', 'knowledge'],
            'status' => 'validated',
            'created_at' => now()->subDays(3),
        ]);

        Entry::factory()->create([
            'title' => 'Old Knowledge Milestone',
            'tags' => ['milestone', 'knowledge'],
            'status' => 'validated',
            'created_at' => now()->subDays(20),
        ]);

        Entry::factory()->create([
            'title' => 'Recent Other Milestone',
            'tags' => ['milestone', 'other'],
            'status' => 'validated',
            'created_at' => now()->subDays(2),
        ]);

        Process::fake();

        $this->artisan('milestones --since=7 --project=knowledge')
            ->expectsOutputToContain('Recent Knowledge Milestone')
            ->doesntExpectOutputToContain('Old Knowledge Milestone')
            ->doesntExpectOutputToContain('Recent Other Milestone')
            ->assertSuccessful();
    });

    // Date Grouping Tests
    it('groups milestones by date', function () {
        Entry::factory()->create([
            'title' => 'Today Milestone',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now(),
        ]);

        Entry::factory()->create([
            'title' => 'This Week Milestone',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now()->subDays(3),
        ]);

        Entry::factory()->create([
            'title' => 'Older Milestone',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now()->subDays(10),
        ]);

        Process::fake();

        $this->artisan('milestones --since=30')
            ->expectsOutputToContain('Today')
            ->expectsOutputToContain('This Week')
            ->expectsOutputToContain('Older')
            ->assertSuccessful();
    });

    it('only shows Today group when all milestones are today', function () {
        Entry::factory()->create([
            'title' => 'Today Milestone 1',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now(),
        ]);

        Entry::factory()->create([
            'title' => 'Today Milestone 2',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now()->subHour(),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('Today')
            ->doesntExpectOutputToContain('This Week')
            ->doesntExpectOutputToContain('Older')
            ->assertSuccessful();
    });

    // GitHub Integration Tests
    it('displays merged pull requests when available', function () {
        Process::preventStrayProcesses();

        Process::fake([
            function ($process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
                if (str_starts_with($command, 'git')) {
                    return Process::result(output: 'git@github.com:conduit-ui/knowledge.git');
                }
                if (str_starts_with($command, 'gh pr list')) {
                    return Process::result(
                        output: json_encode([
                            [
                                'number' => 67,
                                'title' => 'feat: implement blockers command',
                                'mergedAt' => now()->subDay()->toIso8601String(),
                                'url' => 'https://github.com/conduit-ui/knowledge/pull/67',
                            ],
                        ])
                    );
                }
                if (str_starts_with($command, 'gh issue list')) {
                    return Process::result(output: '[]');
                }
            },
        ]);

        $this->artisan('milestones')
            ->expectsOutputToContain('Merged Pull Requests')
            ->expectsOutputToContain('This Week')
            ->expectsOutputToContain('67')  // PR number
            ->assertSuccessful();
    });

    it('displays closed issues when available', function () {
        Process::preventStrayProcesses();

        Process::fake([
            function ($process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
                if (str_starts_with($command, 'git')) {
                    return Process::result(output: 'git@github.com:conduit-ui/knowledge.git');
                }
                if (str_starts_with($command, 'gh pr list')) {
                    return Process::result(output: '[]');
                }
                if (str_starts_with($command, 'gh issue list')) {
                    return Process::result(
                        output: json_encode([
                            [
                                'number' => 47,
                                'title' => 'Add --push flag to sync command',
                                'closedAt' => now()->subDays(2)->toIso8601String(),
                                'url' => 'https://github.com/conduit-ui/knowledge/issues/47',
                            ],
                        ])
                    );
                }
            },
        ]);

        $this->artisan('milestones')
            ->expectsOutputToContain('Closed Issues')
            ->expectsOutputToContain('This Week')
            ->expectsOutputToContain('47')
            ->assertSuccessful();
    });

    it('handles gh command failures gracefully', function () {
        Process::fake([
            'git remote get-url origin' => Process::result(exitCode: 1),
            '*' => Process::result(exitCode: 1),
        ]);

        Entry::factory()->create([
            'title' => 'Milestone',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now(),
        ]);

        $this->artisan('milestones')
            ->expectsOutputToContain('No merged PRs found')
            ->expectsOutputToContain('No closed issues found')
            ->assertSuccessful();
    });

    it('filters GitHub PRs by date', function () {
        Process::preventStrayProcesses();

        Process::fake([
            function ($process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
                if (str_starts_with($command, 'git')) {
                    return Process::result(output: 'git@github.com:conduit-ui/knowledge.git');
                }
                if (str_starts_with($command, 'gh pr list')) {
                    return Process::result(
                        output: json_encode([
                            [
                                'number' => 67,
                                'title' => 'Recent PR',
                                'mergedAt' => now()->subDays(2)->toIso8601String(),
                                'url' => 'https://github.com/conduit-ui/knowledge/pull/67',
                            ],
                            [
                                'number' => 50,
                                'title' => 'Old PR',
                                'mergedAt' => now()->subDays(30)->toIso8601String(),
                                'url' => 'https://github.com/conduit-ui/knowledge/pull/50',
                            ],
                        ])
                    );
                }
                if (str_starts_with($command, 'gh issue list')) {
                    return Process::result(output: '[]');
                }
            },
        ]);

        $this->artisan('milestones --since=7')
            ->expectsOutputToContain('Recent PR')
            ->doesntExpectOutputToContain('Old PR')
            ->assertSuccessful();
    });

    it('filters GitHub issues by date', function () {
        Process::preventStrayProcesses();

        Process::fake([
            function ($process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
                if (str_starts_with($command, 'git')) {
                    return Process::result(output: 'git@github.com:conduit-ui/knowledge.git');
                }
                if (str_starts_with($command, 'gh pr list')) {
                    return Process::result(output: '[]');
                }
                if (str_starts_with($command, 'gh issue list')) {
                    return Process::result(
                        output: json_encode([
                            [
                                'number' => 47,
                                'title' => 'Recent Issue',
                                'closedAt' => now()->subDays(3)->toIso8601String(),
                                'url' => 'https://github.com/conduit-ui/knowledge/issues/47',
                            ],
                            [
                                'number' => 30,
                                'title' => 'Old Issue',
                                'closedAt' => now()->subDays(20)->toIso8601String(),
                                'url' => 'https://github.com/conduit-ui/knowledge/issues/30',
                            ],
                        ])
                    );
                }
            },
        ]);

        $this->artisan('milestones --since=7')
            ->expectsOutputToContain('Recent Issue')
            ->doesntExpectOutputToContain('Old Issue')
            ->assertSuccessful();
    });

    it('filters GitHub PRs by project keyword in title', function () {
        Process::preventStrayProcesses();

        Process::fake([
            function ($process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
                if (str_starts_with($command, 'git')) {
                    return Process::result(output: 'git@github.com:conduit-ui/knowledge.git');
                }
                if (str_starts_with($command, 'gh pr list')) {
                    return Process::result(
                        output: json_encode([
                            [
                                'number' => 67,
                                'title' => 'feat(knowledge): add new feature',
                                'mergedAt' => now()->subDay()->toIso8601String(),
                                'url' => 'https://github.com/conduit-ui/knowledge/pull/67',
                            ],
                            [
                                'number' => 68,
                                'title' => 'feat(other): add different feature',
                                'mergedAt' => now()->subDay()->toIso8601String(),
                                'url' => 'https://github.com/conduit-ui/knowledge/pull/68',
                            ],
                        ])
                    );
                }
                if (str_starts_with($command, 'gh issue list')) {
                    return Process::result(output: '[]');
                }
            },
        ]);

        $this->artisan('milestones --project=knowledge')
            ->expectsOutputToContain('67')
            ->expectsOutputToContain('knowledge')
            ->doesntExpectOutputToContain('68')
            ->assertSuccessful();
    });

    it('filters GitHub issues by project keyword in title', function () {
        Process::preventStrayProcesses();

        Process::fake([
            function ($process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
                if (str_starts_with($command, 'git')) {
                    return Process::result(output: 'git@github.com:conduit-ui/knowledge.git');
                }
                if (str_starts_with($command, 'gh pr list')) {
                    return Process::result(output: '[]');
                }
                if (str_starts_with($command, 'gh issue list')) {
                    return Process::result(
                        output: json_encode([
                            [
                                'number' => 47,
                                'title' => 'knowledge sync issues',
                                'closedAt' => now()->subDay()->toIso8601String(),
                                'url' => 'https://github.com/conduit-ui/knowledge/issues/47',
                            ],
                            [
                                'number' => 48,
                                'title' => 'other module bug',
                                'closedAt' => now()->subDay()->toIso8601String(),
                                'url' => 'https://github.com/conduit-ui/knowledge/issues/48',
                            ],
                        ])
                    );
                }
            },
        ]);

        $this->artisan('milestones --project=knowledge')
            ->expectsOutputToContain('47')
            ->doesntExpectOutputToContain('48')
            ->assertSuccessful();
    });

    it('groups GitHub PRs by date', function () {
        $this->freezeTime();
        $frozenNow = now();

        Process::fake([
            '*git*remote*get-url*origin*' => Process::result(
                output: 'git@github.com:conduit-ui/knowledge.git'
            ),
            '*gh*pr*list*--repo*conduit-ui/knowledge*--state*merged*' => Process::result(
                output: json_encode([
                    [
                        'number' => 70,
                        'title' => 'Today PR',
                        'mergedAt' => $frozenNow->toIso8601String(),
                        'url' => 'https://github.com/conduit-ui/knowledge/pull/70',
                    ],
                    [
                        'number' => 69,
                        'title' => 'This Week PR',
                        'mergedAt' => $frozenNow->copy()->subDays(4)->toIso8601String(),
                        'url' => 'https://github.com/conduit-ui/knowledge/pull/69',
                    ],
                    [
                        'number' => 68,
                        'title' => 'Older PR',
                        'mergedAt' => $frozenNow->copy()->subDays(10)->toIso8601String(),
                        'url' => 'https://github.com/conduit-ui/knowledge/pull/68',
                    ],
                ])
            ),
            '*gh*issue*list*--repo*conduit-ui/knowledge*--state*closed*' => Process::result(
                output: '[]'
            ),
        ]);

        $this->artisan('milestones --since=30')
            ->expectsOutputToContain('Today')
            ->expectsOutputToContain('This Week')
            ->expectsOutputToContain('Older')
            ->assertSuccessful();
    });

    it('groups GitHub issues by date', function () {
        $this->freezeTime();
        $frozenNow = now();

        Process::fake([
            '*git*remote*get-url*origin*' => Process::result(
                output: 'git@github.com:conduit-ui/knowledge.git'
            ),
            '*gh*pr*list*--repo*conduit-ui/knowledge*--state*merged*' => Process::result(
                output: '[]'
            ),
            '*gh*issue*list*--repo*conduit-ui/knowledge*--state*closed*' => Process::result(
                output: json_encode([
                    [
                        'number' => 50,
                        'title' => 'Today Issue',
                        'closedAt' => $frozenNow->toIso8601String(),
                        'url' => 'https://github.com/conduit-ui/knowledge/issues/50',
                    ],
                    [
                        'number' => 49,
                        'title' => 'This Week Issue',
                        'closedAt' => $frozenNow->copy()->subDays(5)->toIso8601String(),
                        'url' => 'https://github.com/conduit-ui/knowledge/issues/49',
                    ],
                    [
                        'number' => 48,
                        'title' => 'Older Issue',
                        'closedAt' => $frozenNow->copy()->subDays(15)->toIso8601String(),
                        'url' => 'https://github.com/conduit-ui/knowledge/issues/48',
                    ],
                ])
            ),
        ]);

        $this->artisan('milestones --since=30')
            ->expectsOutputToContain('Today')
            ->expectsOutputToContain('This Week')
            ->expectsOutputToContain('Older')
            ->assertSuccessful();
    });

    it('detects repository from HTTPS git remote', function () {
        Process::fake([
            'git remote get-url origin' => Process::result(
                output: 'https://github.com/conduit-ui/knowledge.git'
            ),
            '*gh*pr*list*--repo*conduit-ui/knowledge*--state*merged*' => Process::result(
                output: '[]'
            ),
            '*gh*issue*list*--repo*conduit-ui/knowledge*--state*closed*' => Process::result(
                output: '[]'
            ),
        ]);

        $this->artisan('milestones')
            ->assertSuccessful();
    });

    it('detects repository from SSH git remote', function () {
        Process::fake([
            '*git*remote*get-url*origin*' => Process::result(
                output: 'git@github.com:conduit-ui/knowledge.git'
            ),
            '*gh*pr*list*--repo*conduit-ui/knowledge*--state*merged*' => Process::result(
                output: '[]'
            ),
            '*gh*issue*list*--repo*conduit-ui/knowledge*--state*closed*' => Process::result(
                output: '[]'
            ),
        ]);

        $this->artisan('milestones')
            ->assertSuccessful();
    });

    it('falls back to default repo when git remote fails', function () {
        Process::fake([
            'git remote get-url origin' => Process::result(exitCode: 1),
            '*gh*pr*list*--repo*conduit-ui/knowledge*--state*merged*' => Process::result(
                output: '[]'
            ),
            '*gh*issue*list*--repo*conduit-ui/knowledge*--state*closed*' => Process::result(
                output: '[]'
            ),
        ]);

        $this->artisan('milestones')
            ->assertSuccessful();
    });

    // Edge Cases
    it('shows no milestones message when none exist', function () {
        Process::fake([
            '*git*remote*get-url*origin*' => Process::result(
                output: 'git@github.com:conduit-ui/knowledge.git'
            ),
            '*gh*pr*list*--repo*conduit-ui/knowledge*--state*merged*' => Process::result(
                output: '[]'
            ),
            '*gh*issue*list*--repo*conduit-ui/knowledge*--state*closed*' => Process::result(
                output: '[]'
            ),
        ]);

        $this->artisan('milestones')
            ->expectsOutputToContain('No milestones found')
            ->assertSuccessful();
    });

    it('extracts milestone details from content', function () {
        Entry::factory()->create([
            'title' => 'Milestone with Details',
            'content' => "## Milestones\n- ✅ PR #173: Resolved conflicts\n- ✅ KNOWLEDGE_API_TOKEN configured",
            'status' => 'validated',
            'created_at' => now(),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('PR #173')
            ->expectsOutputToContain('KNOWLEDGE_API_TOKEN')
            ->assertSuccessful();
    });

    it('handles entries with multiple detection patterns', function () {
        Entry::factory()->create([
            'title' => 'Multi-Pattern Milestone',
            'content' => "## Milestones\n- ✅ First win\n- ✅ Second win",
            'tags' => ['milestone', 'accomplished'],
            'status' => 'validated',
            'created_at' => now(),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('Multi-Pattern Milestone')
            ->assertSuccessful();
    });

    it('handles entries with only checkmark in content', function () {
        Entry::factory()->create([
            'title' => 'Checkmark Only Milestone',
            'content' => 'Work completed ✅',
            'status' => 'validated',
            'created_at' => now(),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('Checkmark Only Milestone')
            ->assertSuccessful();
    });

    it('handles entries with completed tag', function () {
        Entry::factory()->create([
            'title' => 'Completed Tagged Milestone',
            'tags' => ['completed'],
            'status' => 'validated',
            'created_at' => now(),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('Completed Tagged Milestone')
            ->assertSuccessful();
    });

    it('extracts bullet points with checkmarks', function () {
        Entry::factory()->create([
            'title' => 'Bullet Point Milestone',
            'content' => "## Milestones\n• ✅ First accomplishment\n• ✅ Second accomplishment",
            'status' => 'validated',
            'created_at' => now(),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('First accomplishment')
            ->expectsOutputToContain('Second accomplishment')
            ->assertSuccessful();
    });

    it('handles malformed JSON from GitHub CLI', function () {
        Process::preventStrayProcesses();

        Process::fake([
            function ($process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
                if (str_starts_with($command, 'git')) {
                    return Process::result(output: 'git@github.com:conduit-ui/knowledge.git');
                }
                if (str_starts_with($command, 'gh pr list')) {
                    return Process::result(output: 'invalid json');
                }
                if (str_starts_with($command, 'gh issue list')) {
                    return Process::result(output: 'invalid json');
                }
            },
        ]);

        Entry::factory()->create([
            'title' => 'Milestone',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now(),
        ]);

        $this->artisan('milestones')
            ->expectsOutputToContain('No merged PRs found')
            ->expectsOutputToContain('No closed issues found')
            ->assertSuccessful();
    });

    it('handles GitHub items with missing date fields', function () {
        Process::preventStrayProcesses();

        Process::fake([
            function ($process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
                if (str_starts_with($command, 'git')) {
                    return Process::result(output: 'git@github.com:conduit-ui/knowledge.git');
                }
                if (str_starts_with($command, 'gh pr list')) {
                    return Process::result(
                        output: json_encode([
                            [
                                'number' => 67,
                                'title' => 'PR without mergedAt',
                                'url' => 'https://github.com/conduit-ui/knowledge/pull/67',
                            ],
                        ])
                    );
                }
                if (str_starts_with($command, 'gh issue list')) {
                    return Process::result(
                        output: json_encode([
                            [
                                'number' => 47,
                                'title' => 'Issue without closedAt',
                                'url' => 'https://github.com/conduit-ui/knowledge/issues/47',
                            ],
                        ])
                    );
                }
            },
        ]);

        $this->artisan('milestones')
            ->assertSuccessful();
    });

    it('handles repository URL without .git suffix', function () {
        Process::fake([
            'git remote get-url origin' => Process::result(
                output: 'git@github.com:conduit-ui/knowledge'
            ),
            '*gh*pr*list*--repo*conduit-ui/knowledge*--state*merged*' => Process::result(
                output: '[]'
            ),
            '*gh*issue*list*--repo*conduit-ui/knowledge*--state*closed*' => Process::result(
                output: '[]'
            ),
        ]);

        $this->artisan('milestones')
            ->assertSuccessful();
    });

    it('displays green color scheme output', function () {
        Entry::factory()->create([
            'title' => 'Green Milestone',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now(),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('Green Milestone')
            ->assertSuccessful();
    });

    it('displays header with time range', function () {
        Process::fake();

        $this->artisan('milestones --since=14')
            ->expectsOutputToContain('Last 14 days')
            ->assertSuccessful();
    });

    it('displays project filter in output when specified', function () {
        Process::fake();

        $this->artisan('milestones --project=knowledge')
            ->expectsOutputToContain('Project: knowledge')
            ->assertSuccessful();
    });

    it('shows Knowledge Milestones section header', function () {
        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('Knowledge Milestones')
            ->assertSuccessful();
    });

    it('shows Merged Pull Requests section header', function () {
        Process::fake([
            '*git*remote*get-url*origin*' => Process::result(
                output: 'git@github.com:conduit-ui/knowledge.git'
            ),
            '*gh*pr*list*--repo*conduit-ui/knowledge*--state*merged*' => Process::result(
                output: '[]'
            ),
            '*gh*issue*list*--repo*conduit-ui/knowledge*--state*closed*' => Process::result(
                output: '[]'
            ),
        ]);

        $this->artisan('milestones')
            ->expectsOutputToContain('Merged Pull Requests')
            ->assertSuccessful();
    });

    it('shows Closed Issues section header', function () {
        Process::fake([
            '*git*remote*get-url*origin*' => Process::result(
                output: 'git@github.com:conduit-ui/knowledge.git'
            ),
            '*gh*pr*list*--repo*conduit-ui/knowledge*--state*merged*' => Process::result(
                output: '[]'
            ),
            '*gh*issue*list*--repo*conduit-ui/knowledge*--state*closed*' => Process::result(
                output: '[]'
            ),
        ]);

        $this->artisan('milestones')
            ->expectsOutputToContain('Closed Issues')
            ->assertSuccessful();
    });

    it('handles empty GitHub PR array', function () {
        Process::preventStrayProcesses();

        Process::fake([
            function ($process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
                if (str_starts_with($command, 'git')) {
                    return Process::result(output: 'git@github.com:conduit-ui/knowledge.git');
                }
                if (str_starts_with($command, 'gh pr list')) {
                    return Process::result(output: '[]');
                }
                if (str_starts_with($command, 'gh issue list')) {
                    return Process::result(output: '[]');
                }
            },
        ]);

        $this->artisan('milestones')
            ->expectsOutputToContain('No merged PRs found')
            ->assertSuccessful();
    });

    it('handles empty GitHub issue array', function () {
        Process::preventStrayProcesses();

        Process::fake([
            function ($process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
                if (str_starts_with($command, 'git')) {
                    return Process::result(output: 'git@github.com:conduit-ui/knowledge.git');
                }
                if (str_starts_with($command, 'gh pr list')) {
                    return Process::result(output: '[]');
                }
                if (str_starts_with($command, 'gh issue list')) {
                    return Process::result(output: '[]');
                }
            },
        ]);

        $this->artisan('milestones')
            ->expectsOutputToContain('No closed issues found')
            ->assertSuccessful();
    });

    it('processes multiple milestones in order by date', function () {
        Entry::factory()->create([
            'title' => 'Oldest Milestone',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now()->subDays(5),
        ]);

        Entry::factory()->create([
            'title' => 'Newest Milestone',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now()->subDay(),
        ]);

        Entry::factory()->create([
            'title' => 'Middle Milestone',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now()->subDays(3),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('Oldest Milestone')
            ->expectsOutputToContain('Newest Milestone')
            ->expectsOutputToContain('Middle Milestone')
            ->assertSuccessful();
    });

    it('extracts details from milestone section without bullet prefix', function () {
        Entry::factory()->create([
            'title' => 'Plain Text Milestone',
            'content' => "## Milestones\n✅ First item without bullet\n✅ Second item without bullet",
            'status' => 'validated',
            'created_at' => now(),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain('First item without bullet')
            ->expectsOutputToContain('Second item without bullet')
            ->assertSuccessful();
    });

    it('shows milestone entry ID in output', function () {
        $milestone = Entry::factory()->create([
            'title' => 'Milestone with ID',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now(),
        ]);

        Process::fake();

        $this->artisan('milestones')
            ->expectsOutputToContain("[{$milestone->id}]")
            ->assertSuccessful();
    });

    it('successfully completes with all data sources', function () {
        Entry::factory()->create([
            'title' => 'Knowledge Milestone',
            'tags' => ['milestone'],
            'status' => 'validated',
            'created_at' => now(),
        ]);

        Process::preventStrayProcesses();

        Process::fake([
            function ($process) {
                $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
                if (str_starts_with($command, 'git')) {
                    return Process::result(output: 'git@github.com:conduit-ui/knowledge.git');
                }
                if (str_starts_with($command, 'gh pr list')) {
                    return Process::result(
                        output: json_encode([
                            [
                                'number' => 67,
                                'title' => 'Test PR',
                                'mergedAt' => now()->toIso8601String(),
                                'url' => 'https://github.com/conduit-ui/knowledge/pull/67',
                            ],
                        ])
                    );
                }
                if (str_starts_with($command, 'gh issue list')) {
                    return Process::result(
                        output: json_encode([
                            [
                                'number' => 47,
                                'title' => 'Test Issue',
                                'closedAt' => now()->toIso8601String(),
                                'url' => 'https://github.com/conduit-ui/knowledge/issues/47',
                            ],
                        ])
                    );
                }
            },
        ]);

        $this->artisan('milestones')
            ->expectsOutputToContain('Knowledge Milestone')
            ->expectsOutputToContain('Test PR')
            ->expectsOutputToContain('Test Issue')
            ->assertSuccessful();
    });
});
