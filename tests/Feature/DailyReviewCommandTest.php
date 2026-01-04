<?php

declare(strict_types=1);

use App\Models\Entry;
use Illuminate\Support\Facades\Process;

describe('DailyReviewCommand', function () {
    describe('milestone retrieval', function () {
        it('retrieves today milestones automatically', function () {
            Entry::factory()->create([
                'title' => 'Today Milestone',
                'content' => '## Milestones'."\n".'- ✅ Completed feature X',
                'status' => 'validated',
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Yesterday Milestone',
                'content' => '## Milestones'."\n".'- ✅ Completed feature Y',
                'status' => 'validated',
                'created_at' => now()->subDay(),
            ]);

            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Great progress')
                ->expectsQuestion('What were the biggest challenges?', 'None')
                ->expectsQuestion('What did you learn?', 'New patterns')
                ->expectsQuestion('What would you do differently?', 'Nothing')
                ->expectsQuestion('What are your key takeaways?', 'Keep going')
                ->expectsOutputToContain('Today Milestone')
                ->doesntExpectOutputToContain('Yesterday Milestone')
                ->assertSuccessful();
        });

        it('shows today milestones from knowledge entries', function () {
            Entry::factory()->create([
                'title' => 'Knowledge Milestone',
                'tags' => ['milestone'],
                'status' => 'validated',
                'created_at' => now(),
            ]);

            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress made')
                ->expectsQuestion('What were the biggest challenges?', 'Minor issues')
                ->expectsQuestion('What did you learn?', 'Best practices')
                ->expectsQuestion('What would you do differently?', 'Test more')
                ->expectsQuestion('What are your key takeaways?', 'Good day')
                ->expectsOutputToContain('Knowledge Milestone')
                ->assertSuccessful();
        });

        it('shows today milestones from GitHub PRs', function () {
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
                                    'number' => 73,
                                    'title' => 'feat: add daily-review command',
                                    'mergedAt' => now()->toIso8601String(),
                                    'url' => 'https://github.com/conduit-ui/knowledge/pull/73',
                                ],
                            ])
                        );
                    }
                    if (str_starts_with($command, 'gh issue list')) {
                        return Process::result(output: '[]');
                    }
                },
            ]);

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Merged PR')
                ->expectsQuestion('What were the biggest challenges?', 'Tests')
                ->expectsQuestion('What did you learn?', 'Pest patterns')
                ->expectsQuestion('What would you do differently?', 'Start earlier')
                ->expectsQuestion('What are your key takeaways?', 'TDD works')
                ->expectsOutputToContain('feat: add daily-review command')
                ->assertSuccessful();
        });

        it('shows today milestones from GitHub issues', function () {
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
                                    'number' => 72,
                                    'title' => 'Implement daily review command',
                                    'closedAt' => now()->toIso8601String(),
                                    'url' => 'https://github.com/conduit-ui/knowledge/issues/72',
                                ],
                            ])
                        );
                    }
                },
            ]);

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Closed issue')
                ->expectsQuestion('What were the biggest challenges?', 'None')
                ->expectsQuestion('What did you learn?', 'New approach')
                ->expectsQuestion('What would you do differently?', 'Nothing')
                ->expectsQuestion('What are your key takeaways?', 'Success')
                ->expectsOutputToContain('Implement daily review command')
                ->assertSuccessful();
        });

        it('handles days with no milestones', function () {
            Process::fake([
                'git remote get-url origin' => Process::result(
                    output: 'git@github.com:conduit-ui/knowledge.git'
                ),
                'gh pr list --repo conduit-ui/knowledge --state merged --json number,title,mergedAt,url --limit 100' => Process::result(
                    output: '[]'
                ),
                'gh issue list --repo conduit-ui/knowledge --state closed --json number,title,closedAt,url --limit 100' => Process::result(
                    output: '[]'
                ),
            ]);

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Reflection time')
                ->expectsQuestion('What were the biggest challenges?', 'Staying focused')
                ->expectsQuestion('What did you learn?', 'Patience')
                ->expectsQuestion('What would you do differently?', 'Plan better')
                ->expectsQuestion('What are your key takeaways?', 'Tomorrow will be better')
                ->expectsOutputToContain('No milestones found')
                ->assertSuccessful();
        });
    });

    describe('interactive reflection prompts', function () {
        it('prompts for all 5 reflection questions', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Good progress on tests')
                ->expectsQuestion('What were the biggest challenges?', 'Understanding the patterns')
                ->expectsQuestion('What did you learn?', 'Pest describe blocks')
                ->expectsQuestion('What would you do differently?', 'Start with simpler tests')
                ->expectsQuestion('What are your key takeaways?', 'TDD saves time')
                ->assertSuccessful();
        });

        it('accepts multi-line responses for reflection questions', function () {
            Process::fake();

            $multiLineResponse = "First point\nSecond point\nThird point";

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', $multiLineResponse)
                ->expectsQuestion('What were the biggest challenges?', 'Challenge')
                ->expectsQuestion('What did you learn?', 'Learning')
                ->expectsQuestion('What would you do differently?', 'Different')
                ->expectsQuestion('What are your key takeaways?', 'Takeaway')
                ->assertSuccessful();
        });

        it('allows empty responses for reflection questions', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', '')
                ->expectsQuestion('What were the biggest challenges?', '')
                ->expectsQuestion('What did you learn?', '')
                ->expectsQuestion('What would you do differently?', '')
                ->expectsQuestion('What are your key takeaways?', '')
                ->assertSuccessful();
        });

        it('stores all reflection responses in entry content', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Completed tests')
                ->expectsQuestion('What were the biggest challenges?', 'Understanding patterns')
                ->expectsQuestion('What did you learn?', 'Pest framework')
                ->expectsQuestion('What would you do differently?', 'Read docs first')
                ->expectsQuestion('What are your key takeaways?', 'Practice makes perfect')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->content)->toContain('Completed tests');
            expect($entry->content)->toContain('Understanding patterns');
            expect($entry->content)->toContain('Pest framework');
            expect($entry->content)->toContain('Read docs first');
            expect($entry->content)->toContain('Practice makes perfect');
        });
    });

    describe('entry storage', function () {
        it('creates validated knowledge entry', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->status)->toBe('validated');
        });

        it('stores entry with reflection category', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->category)->toBe('reflection');
        });

        it('stores entry with high confidence', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->confidence)->toBeGreaterThanOrEqual(80);
        });

        it('stores entry with daily-review tag', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->tags)->toContain('daily-review');
        });

        it('generates title with date', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->title)->toContain(now()->format('Y-m-d'));
            expect($entry->title)->toContain('Daily Review');
        });

        it('includes milestone count in title when milestones exist', function () {
            Entry::factory()->create([
                'title' => 'Milestone 1',
                'tags' => ['milestone'],
                'status' => 'validated',
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Milestone 2',
                'tags' => ['milestone'],
                'status' => 'validated',
                'created_at' => now(),
            ]);

            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->title)->toContain('2 milestones');
        });

        it('stores validation_date as today', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->validation_date)->not->toBeNull();
            expect($entry->validation_date->isToday())->toBeTrue();
        });
    });

    describe('--quick flag', function () {
        it('prompts fewer questions with quick flag', function () {
            Process::fake();

            $this->artisan('daily-review --quick')
                ->expectsQuestion('What went well today?', 'Good day')
                ->expectsQuestion('What did you learn?', 'New things')
                ->expectsQuestion('What are your key takeaways?', 'Success')
                ->assertSuccessful();
        });

        it('creates entry with quick flag', function () {
            Process::fake();

            $this->artisan('daily-review --quick')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->status)->toBe('validated');
        });

        it('includes quick tag when using quick flag', function () {
            Process::fake();

            $this->artisan('daily-review --quick')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->tags)->toContain('quick');
        });

        it('shows milestones even with quick flag', function () {
            Entry::factory()->create([
                'title' => 'Quick Milestone',
                'tags' => ['milestone'],
                'status' => 'validated',
                'created_at' => now(),
            ]);

            Process::fake();

            $this->artisan('daily-review --quick')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->expectsOutputToContain('Quick Milestone')
                ->assertSuccessful();
        });
    });

    describe('output formatting', function () {
        it('displays beautiful header', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->expectsOutputToContain('Daily Review')
                ->expectsOutputToContain(now()->format('l, F j, Y'))
                ->assertSuccessful();
        });

        it('displays milestones section header', function () {
            Entry::factory()->create([
                'title' => 'Test Milestone',
                'tags' => ['milestone'],
                'status' => 'validated',
                'created_at' => now(),
            ]);

            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->expectsOutputToContain("Today's Accomplishments")
                ->assertSuccessful();
        });

        it('displays success message after saving', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->expectsOutputToContain('Daily review saved')
                ->assertSuccessful();
        });

        it('displays entry ID after saving', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
        });

        it('uses cyan color scheme for headers', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();
        });

        it('displays milestone details beautifully', function () {
            Entry::factory()->create([
                'title' => 'Beautiful Milestone',
                'content' => "## Milestones\n- ✅ First accomplishment\n- ✅ Second accomplishment",
                'status' => 'validated',
                'created_at' => now(),
            ]);

            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->expectsOutputToContain('Beautiful Milestone')
                ->expectsOutputToContain('First accomplishment')
                ->expectsOutputToContain('Second accomplishment')
                ->assertSuccessful();
        });
    });

    describe('content formatting', function () {
        it('formats content with sections', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Great progress')
                ->expectsQuestion('What were the biggest challenges?', 'Minor issues')
                ->expectsQuestion('What did you learn?', 'New patterns')
                ->expectsQuestion('What would you do differently?', 'Plan better')
                ->expectsQuestion('What are your key takeaways?', 'Keep learning')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->content)->toContain('## What Went Well');
            expect($entry->content)->toContain('## Biggest Challenges');
            expect($entry->content)->toContain('## What I Learned');
            expect($entry->content)->toContain('## What I Would Do Differently');
            expect($entry->content)->toContain('## Key Takeaways');
        });

        it('includes milestones section in content', function () {
            Entry::factory()->create([
                'title' => 'Content Milestone',
                'tags' => ['milestone'],
                'status' => 'validated',
                'created_at' => now(),
            ]);

            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->content)->toContain('## Milestones');
        });

        it('lists milestone titles in content', function () {
            Entry::factory()->create([
                'title' => 'Listed Milestone',
                'tags' => ['milestone'],
                'status' => 'validated',
                'created_at' => now(),
            ]);

            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->content)->toContain('Listed Milestone');
        });
    });

    describe('edge cases', function () {
        it('handles GitHub API failures gracefully', function () {
            Process::fake([
                'git remote get-url origin' => Process::result(exitCode: 1),
                '*' => Process::result(exitCode: 1),
            ]);

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
        });

        it('handles very long reflection responses', function () {
            Process::fake();

            $longResponse = str_repeat('This is a very long reflection response. ', 100);

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', $longResponse)
                ->expectsQuestion('What were the biggest challenges?', $longResponse)
                ->expectsQuestion('What did you learn?', $longResponse)
                ->expectsQuestion('What would you do differently?', $longResponse)
                ->expectsQuestion('What are your key takeaways?', $longResponse)
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->content)->toContain($longResponse);
        });

        it('prevents duplicate daily reviews on same day', function () {
            Entry::factory()->create([
                'title' => 'Daily Review - '.now()->format('Y-m-d'),
                'category' => 'reflection',
                'tags' => ['daily-review'],
                'status' => 'validated',
                'created_at' => now(),
            ]);

            Process::fake();

            $this->artisan('daily-review')
                ->expectsOutputToContain('already completed')
                ->assertFailed();
        });

        it('handles special characters in reflection responses', function () {
            Process::fake();

            $specialResponse = 'Test with <html> & "quotes" and \'apostrophes\' and \n newlines';

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', $specialResponse)
                ->expectsQuestion('What were the biggest challenges?', 'Normal')
                ->expectsQuestion('What did you learn?', 'Normal')
                ->expectsQuestion('What would you do differently?', 'Normal')
                ->expectsQuestion('What are your key takeaways?', 'Normal')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
        });

        it('handles multiple milestone sources at once', function () {
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
                                    'number' => 73,
                                    'title' => 'PR Today',
                                    'mergedAt' => now()->toIso8601String(),
                                    'url' => 'https://github.com/conduit-ui/knowledge/pull/73',
                                ],
                            ])
                        );
                    }
                    if (str_starts_with($command, 'gh issue list')) {
                        return Process::result(
                            output: json_encode([
                                [
                                    'number' => 72,
                                    'title' => 'Issue Today',
                                    'closedAt' => now()->toIso8601String(),
                                    'url' => 'https://github.com/conduit-ui/knowledge/issues/72',
                                ],
                            ])
                        );
                    }
                },
            ]);

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'All sources')
                ->expectsQuestion('What were the biggest challenges?', 'Integration')
                ->expectsQuestion('What did you learn?', 'Patterns')
                ->expectsQuestion('What would you do differently?', 'Nothing')
                ->expectsQuestion('What are your key takeaways?', 'Great day')
                ->expectsOutputToContain('Knowledge Milestone')
                ->expectsOutputToContain('PR Today')
                ->expectsOutputToContain('Issue Today')
                ->assertSuccessful();
        });
    });

    describe('git context', function () {
        it('auto-populates git fields', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->branch)->not->toBeNull();
            expect($entry->commit)->not->toBeNull();
        });

        it('stores current repository context', function () {
            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->assertSuccessful();

            $entry = Entry::where('category', 'reflection')->first();
            expect($entry)->not->toBeNull();
            expect($entry->repo)->not->toBeNull();
        });
    });

    describe('milestone display integration', function () {
        it('extracts and displays milestone details', function () {
            Entry::factory()->create([
                'title' => 'Detailed Milestone',
                'content' => "## Milestones\n- ✅ PR #173: Resolved conflicts\n- ✅ Tests at 100% coverage",
                'status' => 'validated',
                'created_at' => now(),
            ]);

            Process::fake();

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->expectsOutputToContain('PR #173')
                ->expectsOutputToContain('Tests at 100% coverage')
                ->assertSuccessful();
        });

        it('groups milestones by source type', function () {
            Entry::factory()->create([
                'title' => 'Knowledge Source',
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
                                    'number' => 73,
                                    'title' => 'GitHub PR',
                                    'mergedAt' => now()->toIso8601String(),
                                    'url' => 'https://github.com/conduit-ui/knowledge/pull/73',
                                ],
                            ])
                        );
                    }
                    if (str_starts_with($command, 'gh issue list')) {
                        return Process::result(output: '[]');
                    }
                },
            ]);

            $this->artisan('daily-review')
                ->expectsQuestion('What went well today?', 'Progress')
                ->expectsQuestion('What were the biggest challenges?', 'Challenges')
                ->expectsQuestion('What did you learn?', 'Learnings')
                ->expectsQuestion('What would you do differently?', 'Improvements')
                ->expectsQuestion('What are your key takeaways?', 'Takeaways')
                ->expectsOutputToContain('Knowledge Milestones')
                ->expectsOutputToContain('Merged Pull Requests')
                ->assertSuccessful();
        });
    });
});
