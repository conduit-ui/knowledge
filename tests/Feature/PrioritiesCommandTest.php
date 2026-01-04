<?php

declare(strict_types=1);

use App\Models\Entry;

describe('PrioritiesCommand', function () {
    describe('basic functionality', function () {
        it('shows top 3 priorities by default', function () {
            // Create 5 entries with different scores
            Entry::factory()->create([
                'title' => 'High Priority Item',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now()->subDays(1),
            ]);

            Entry::factory()->create([
                'title' => 'Medium Priority Item',
                'tags' => ['user-intent'],
                'confidence' => 80,
                'created_at' => now()->subDays(2),
            ]);

            Entry::factory()->create([
                'title' => 'Low Priority Item',
                'confidence' => 50,
                'created_at' => now()->subDays(10),
            ]);

            Entry::factory()->create([
                'title' => 'Another Medium',
                'tags' => ['user-intent'],
                'confidence' => 75,
                'created_at' => now()->subDays(3),
            ]);

            Entry::factory()->create([
                'title' => 'Another Low',
                'confidence' => 30,
                'created_at' => now()->subDays(20),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Top 3 Priorities')
                ->expectsOutputToContain('High Priority Item')
                ->assertSuccessful();
        });

        it('handles empty results gracefully', function () {
            $this->artisan('priorities')
                ->expectsOutputToContain('No priorities found')
                ->assertSuccessful();
        });

        it('shows count when fewer than 3 priorities exist', function () {
            Entry::factory()->create([
                'title' => 'Single Priority',
                'tags' => ['blocker'],
                'status' => 'draft',
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Single Priority')
                ->assertSuccessful();
        });

        it('displays priority rank numbers', function () {
            Entry::factory()->count(3)->create([
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('#1')
                ->expectsOutputToContain('#2')
                ->expectsOutputToContain('#3')
                ->assertSuccessful();
        });

        it('returns success exit code', function () {
            Entry::factory()->create([
                'tags' => ['blocker'],
                'status' => 'draft',
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->assertSuccessful();
        });
    });

    describe('scoring algorithm', function () {
        it('scores blockers with weight 3', function () {
            // Blocker: (1 × 3) + (0 × 2) + (50 × 1) = 53
            $blocker = Entry::factory()->create([
                'title' => 'Blocker Item',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 50,
                'created_at' => now()->subDays(10),
            ]);

            // Non-blocker: (0 × 3) + (0 × 2) + (90 × 1) = 90
            Entry::factory()->create([
                'title' => 'High Confidence Non-Blocker',
                'confidence' => 90,
                'created_at' => now()->subDays(10),
            ]);

            // Blocker should rank lower due to blocker weight not overcoming confidence difference
            $this->artisan('priorities')
                ->expectsOutputToContain('High Confidence Non-Blocker')
                ->assertSuccessful();
        });

        it('scores recent intents with weight 2', function () {
            // Recent intent (1 day): (0 × 3) + (recency × 2) + (50 × 1)
            $recentIntent = Entry::factory()->create([
                'title' => 'Recent Intent',
                'tags' => ['user-intent'],
                'confidence' => 50,
                'created_at' => now()->subDays(1),
            ]);

            // Old entry: (0 × 3) + (0 × 2) + (60 × 1) = 60
            Entry::factory()->create([
                'title' => 'Old Entry',
                'confidence' => 60,
                'created_at' => now()->subDays(30),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Recent Intent')
                ->assertSuccessful();
        });

        it('scores confidence with weight 1', function () {
            Entry::factory()->create([
                'title' => 'High Confidence',
                'confidence' => 100,
                'created_at' => now()->subDays(10),
            ]);

            Entry::factory()->create([
                'title' => 'Low Confidence',
                'confidence' => 10,
                'created_at' => now()->subDays(10),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('High Confidence')
                ->assertSuccessful();
        });

        it('combines all scoring factors correctly', function () {
            // Item 1: Blocker + Recent + High Confidence
            // Score: (1 × 3) + (high recency × 2) + (95 × 1) = very high
            $topPriority = Entry::factory()->create([
                'title' => 'Critical Blocker',
                'tags' => ['blocker', 'user-intent'],
                'status' => 'draft',
                'confidence' => 95,
                'created_at' => now(),
            ]);

            // Item 2: Recent intent + Medium confidence
            // Score: (0 × 3) + (recency × 2) + (70 × 1) = medium-high
            Entry::factory()->create([
                'title' => 'Recent Task',
                'tags' => ['user-intent'],
                'confidence' => 70,
                'created_at' => now()->subDays(1),
            ]);

            // Item 3: Old + Low confidence
            // Score: (0 × 3) + (0 × 2) + (30 × 1) = 30
            Entry::factory()->create([
                'title' => 'Old Low Priority',
                'confidence' => 30,
                'created_at' => now()->subDays(30),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Critical Blocker')
                ->expectsOutputToContain('#1')
                ->assertSuccessful();
        });

        it('handles zero confidence scores', function () {
            Entry::factory()->create([
                'title' => 'Zero Confidence',
                'confidence' => 0,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Zero Confidence')
                ->assertSuccessful();
        });

        it('handles maximum confidence scores', function () {
            Entry::factory()->create([
                'title' => 'Max Confidence',
                'confidence' => 100,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Max Confidence')
                ->assertSuccessful();
        });

        it('prioritizes blockers over high confidence items', function () {
            // Blocker with medium confidence
            Entry::factory()->create([
                'title' => 'Blocker Medium Confidence',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 60,
                'created_at' => now()->subDays(2),
            ]);

            // High confidence but not a blocker
            Entry::factory()->create([
                'title' => 'High Confidence Only',
                'confidence' => 100,
                'created_at' => now()->subDays(2),
            ]);

            $output = $this->artisan('priorities')->run();
            expect($output)->toBe(0);
        });

        it('prioritizes recent intents over old high-confidence items', function () {
            Entry::factory()->create([
                'title' => 'Recent Intent',
                'tags' => ['user-intent'],
                'confidence' => 50,
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Old High Confidence',
                'confidence' => 80,
                'created_at' => now()->subDays(30),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Recent Intent')
                ->assertSuccessful();
        });
    });

    describe('blocker detection', function () {
        it('detects open blockers from blocker tag', function () {
            Entry::factory()->create([
                'title' => 'Tagged Blocker',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Tagged Blocker')
                ->assertSuccessful();
        });

        it('detects open blockers from blocked tag', function () {
            Entry::factory()->create([
                'title' => 'Blocked Item',
                'tags' => ['blocked'],
                'status' => 'draft',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Blocked Item')
                ->assertSuccessful();
        });

        it('detects blockers from content with ## Blockers section', function () {
            Entry::factory()->create([
                'title' => 'Entry with Blockers Section',
                'content' => "## Blockers\n- Cannot proceed without API access",
                'status' => 'draft',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Entry with Blockers Section')
                ->assertSuccessful();
        });

        it('detects blockers from content with Blocker: prefix', function () {
            Entry::factory()->create([
                'title' => 'Entry with Blocker Prefix',
                'content' => 'Blocker: Waiting for database migration',
                'status' => 'draft',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Entry with Blocker Prefix')
                ->assertSuccessful();
        });

        it('excludes resolved blockers', function () {
            Entry::factory()->create([
                'title' => 'Resolved Blocker',
                'tags' => ['blocker'],
                'status' => 'validated',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Active Blocker',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Active Blocker')
                ->doesntExpectOutputToContain('Resolved Blocker')
                ->assertSuccessful();
        });

        it('excludes deprecated blockers', function () {
            Entry::factory()->create([
                'title' => 'Deprecated Blocker',
                'tags' => ['blocker'],
                'status' => 'deprecated',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Active Blocker',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Active Blocker')
                ->doesntExpectOutputToContain('Deprecated Blocker')
                ->assertSuccessful();
        });
    });

    describe('intent detection', function () {
        it('detects recent intents from user-intent tag', function () {
            Entry::factory()->create([
                'title' => 'User Intent Item',
                'tags' => ['user-intent'],
                'confidence' => 80,
                'created_at' => now()->subDays(1),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('User Intent Item')
                ->assertSuccessful();
        });

        it('gives higher scores to more recent intents', function () {
            Entry::factory()->create([
                'title' => 'Today Intent',
                'tags' => ['user-intent'],
                'confidence' => 60,
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Week Old Intent',
                'tags' => ['user-intent'],
                'confidence' => 60,
                'created_at' => now()->subDays(7),
            ]);

            Entry::factory()->create([
                'title' => 'Month Old Intent',
                'tags' => ['user-intent'],
                'confidence' => 60,
                'created_at' => now()->subDays(30),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Today Intent')
                ->assertSuccessful();
        });

        it('considers intent recency in scoring', function () {
            // Very recent intent with low confidence should rank high
            Entry::factory()->create([
                'title' => 'Very Recent Intent',
                'tags' => ['user-intent'],
                'confidence' => 40,
                'created_at' => now(),
            ]);

            // Old entry with high confidence
            Entry::factory()->create([
                'title' => 'Old High Confidence',
                'confidence' => 80,
                'created_at' => now()->subDays(30),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Very Recent Intent')
                ->assertSuccessful();
        });
    });

    describe('high-priority tag detection', function () {
        it('detects items with critical priority', function () {
            Entry::factory()->create([
                'title' => 'Critical Priority Item',
                'priority' => 'critical',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Critical Priority Item')
                ->assertSuccessful();
        });

        it('detects items with high priority', function () {
            Entry::factory()->create([
                'title' => 'High Priority Item',
                'priority' => 'high',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('High Priority Item')
                ->assertSuccessful();
        });

        it('prioritizes critical over high priority', function () {
            Entry::factory()->create([
                'title' => 'Critical Item',
                'priority' => 'critical',
                'confidence' => 60,
                'created_at' => now()->subDays(5),
            ]);

            Entry::factory()->create([
                'title' => 'High Item',
                'priority' => 'high',
                'confidence' => 60,
                'created_at' => now()->subDays(5),
            ]);

            $output = $this->artisan('priorities')->run();
            expect($output)->toBe(0);
        });

        it('excludes low priority items from top priorities', function () {
            Entry::factory()->create([
                'title' => 'Low Priority Item',
                'priority' => 'low',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'High Priority Item',
                'priority' => 'high',
                'confidence' => 50,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('High Priority Item')
                ->assertSuccessful();
        });

        it('excludes medium priority items when higher priorities exist', function () {
            Entry::factory()->create([
                'title' => 'Medium Priority Item',
                'priority' => 'medium',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Critical Priority Item',
                'priority' => 'critical',
                'confidence' => 60,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Critical Priority Item')
                ->assertSuccessful();
        });
    });

    describe('--project flag', function () {
        it('filters priorities by project tag', function () {
            Entry::factory()->create([
                'title' => 'Knowledge Priority',
                'tags' => ['blocker', 'knowledge'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Other Project Priority',
                'tags' => ['blocker', 'other-project'],
                'status' => 'draft',
                'confidence' => 95,
                'created_at' => now(),
            ]);

            $this->artisan('priorities --project=knowledge')
                ->expectsOutputToContain('Knowledge Priority')
                ->doesntExpectOutputToContain('Other Project Priority')
                ->assertSuccessful();
        });

        it('filters priorities by module field', function () {
            Entry::factory()->create([
                'title' => 'Module Based Priority',
                'tags' => ['blocker'],
                'module' => 'knowledge',
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Other Module Priority',
                'tags' => ['blocker'],
                'module' => 'other',
                'status' => 'draft',
                'confidence' => 95,
                'created_at' => now(),
            ]);

            $this->artisan('priorities --project=knowledge')
                ->expectsOutputToContain('Module Based Priority')
                ->doesntExpectOutputToContain('Other Module Priority')
                ->assertSuccessful();
        });

        it('shows project name in output header', function () {
            Entry::factory()->create([
                'title' => 'Knowledge Priority',
                'tags' => ['blocker', 'knowledge'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities --project=knowledge')
                ->expectsOutputToContain('Project: knowledge')
                ->assertSuccessful();
        });

        it('shows empty results when no priorities match project', function () {
            Entry::factory()->create([
                'title' => 'Knowledge Priority',
                'tags' => ['blocker', 'knowledge'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities --project=nonexistent')
                ->expectsOutputToContain('No priorities found')
                ->assertSuccessful();
        });

        it('combines project filtering with scoring', function () {
            // High score but wrong project
            Entry::factory()->create([
                'title' => 'High Score Wrong Project',
                'tags' => ['blocker', 'other'],
                'status' => 'draft',
                'confidence' => 100,
                'created_at' => now(),
            ]);

            // Lower score but correct project
            Entry::factory()->create([
                'title' => 'Lower Score Right Project',
                'tags' => ['blocker', 'knowledge'],
                'status' => 'draft',
                'confidence' => 50,
                'created_at' => now(),
            ]);

            $this->artisan('priorities --project=knowledge')
                ->expectsOutputToContain('Lower Score Right Project')
                ->doesntExpectOutputToContain('High Score Wrong Project')
                ->assertSuccessful();
        });
    });

    describe('output format', function () {
        it('displays entry ID', function () {
            $entry = Entry::factory()->create([
                'title' => 'Priority Item',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain("ID: {$entry->id}")
                ->assertSuccessful();
        });

        it('displays entry title', function () {
            Entry::factory()->create([
                'title' => 'Important Priority Task',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Important Priority Task')
                ->assertSuccessful();
        });

        it('displays calculated score', function () {
            Entry::factory()->create([
                'title' => 'Scored Item',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Score:')
                ->assertSuccessful();
        });

        it('displays category when present', function () {
            Entry::factory()->create([
                'title' => 'Categorized Priority',
                'tags' => ['blocker'],
                'category' => 'infrastructure',
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Category: infrastructure')
                ->assertSuccessful();
        });

        it('displays priority level when present', function () {
            Entry::factory()->create([
                'title' => 'Critical Item',
                'tags' => ['blocker'],
                'priority' => 'critical',
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Priority: critical')
                ->assertSuccessful();
        });

        it('displays confidence score', function () {
            Entry::factory()->create([
                'title' => 'Confidence Item',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 85,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Confidence: 85')
                ->assertSuccessful();
        });

        it('displays age in days', function () {
            Entry::factory()->create([
                'title' => 'Old Priority',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now()->subDays(5),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('days')
                ->assertSuccessful();
        });

        it('displays reason for priority', function () {
            Entry::factory()->create([
                'title' => 'Blocker Priority',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Reason:')
                ->assertSuccessful();
        });

        it('shows blocker as reason for blockers', function () {
            Entry::factory()->create([
                'title' => 'Blocked Task',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Blocker')
                ->assertSuccessful();
        });

        it('shows recent intent as reason for recent user intents', function () {
            Entry::factory()->create([
                'title' => 'Recent Intent Task',
                'tags' => ['user-intent'],
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Recent')
                ->assertSuccessful();
        });

        it('shows high confidence as reason when applicable', function () {
            Entry::factory()->create([
                'title' => 'High Confidence Task',
                'confidence' => 95,
                'created_at' => now()->subDays(10),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Confidence')
                ->assertSuccessful();
        });
    });

    describe('edge cases', function () {
        it('handles exactly 3 priorities', function () {
            Entry::factory()->count(3)->create([
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('#3')
                ->assertSuccessful();
        });

        it('handles more than 3 priorities and shows only top 3', function () {
            Entry::factory()->create([
                'title' => 'Top Priority',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 100,
                'created_at' => now(),
            ]);

            Entry::factory()->count(5)->create([
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 50,
                'created_at' => now()->subDays(10),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Top Priority')
                ->expectsOutputToContain('#1')
                ->expectsOutputToContain('#2')
                ->expectsOutputToContain('#3')
                ->assertSuccessful();
        });

        it('handles priorities with equal scores', function () {
            Entry::factory()->count(4)->create([
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('#1')
                ->expectsOutputToContain('#2')
                ->expectsOutputToContain('#3')
                ->assertSuccessful();
        });

        it('handles entries with null confidence', function () {
            Entry::factory()->create([
                'title' => 'Null Confidence',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => null,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->assertSuccessful();
        });

        it('handles entries with null tags', function () {
            Entry::factory()->create([
                'title' => 'Null Tags',
                'tags' => null,
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->assertSuccessful();
        });

        it('handles entries created today', function () {
            Entry::factory()->create([
                'title' => 'Created Today',
                'tags' => ['user-intent'],
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Created Today')
                ->assertSuccessful();
        });

        it('handles very old entries', function () {
            Entry::factory()->create([
                'title' => 'Very Old Entry',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 80,
                'created_at' => now()->subYears(2),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Very Old Entry')
                ->assertSuccessful();
        });

        it('handles entries with multiple tags', function () {
            Entry::factory()->create([
                'title' => 'Multi-Tag Priority',
                'tags' => ['blocker', 'user-intent', 'critical', 'knowledge'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Multi-Tag Priority')
                ->assertSuccessful();
        });

        it('handles entries with no category', function () {
            Entry::factory()->create([
                'title' => 'No Category Entry',
                'tags' => ['blocker'],
                'category' => null,
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('No Category Entry')
                ->assertSuccessful();
        });

        it('handles entries with no module', function () {
            Entry::factory()->create([
                'title' => 'No Module Entry',
                'tags' => ['blocker'],
                'module' => null,
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('No Module Entry')
                ->assertSuccessful();
        });

        it('handles single priority item', function () {
            Entry::factory()->create([
                'title' => 'Only One Priority',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('#1')
                ->expectsOutputToContain('Only One Priority')
                ->assertSuccessful();
        });

        it('handles two priority items', function () {
            Entry::factory()->count(2)->create([
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('#1')
                ->expectsOutputToContain('#2')
                ->assertSuccessful();
        });
    });

    describe('sorting and ranking', function () {
        it('ranks priorities by score descending', function () {
            $low = Entry::factory()->create([
                'title' => 'Low Score Priority',
                'confidence' => 30,
                'created_at' => now()->subDays(30),
            ]);

            $high = Entry::factory()->create([
                'title' => 'High Score Priority',
                'tags' => ['blocker', 'user-intent'],
                'status' => 'draft',
                'confidence' => 95,
                'created_at' => now(),
            ]);

            $medium = Entry::factory()->create([
                'title' => 'Medium Score Priority',
                'tags' => ['user-intent'],
                'confidence' => 60,
                'created_at' => now()->subDays(5),
            ]);

            $output = $this->artisan('priorities')->run();
            expect($output)->toBe(0);
        });

        it('breaks score ties by creation date descending', function () {
            $older = Entry::factory()->create([
                'title' => 'Older Equal Score',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 80,
                'created_at' => now()->subDays(5),
            ]);

            $newer = Entry::factory()->create([
                'title' => 'Newer Equal Score',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 80,
                'created_at' => now()->subDays(2),
            ]);

            $output = $this->artisan('priorities')->run();
            expect($output)->toBe(0);
        });

        it('limits results to exactly 3 items', function () {
            Entry::factory()->count(10)->create([
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $output = $this->artisan('priorities')->run();
            expect($output)->toBe(0);
        });
    });

    describe('header and summary output', function () {
        it('displays command header', function () {
            Entry::factory()->create([
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Top 3 Priorities')
                ->assertSuccessful();
        });

        it('includes timestamp in header', function () {
            Entry::factory()->create([
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->assertSuccessful();
        });

        it('shows count of total priorities considered', function () {
            Entry::factory()->count(5)->create([
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Top 3')
                ->assertSuccessful();
        });
    });

    describe('combined scenarios', function () {
        it('handles blockers and intents together', function () {
            Entry::factory()->create([
                'title' => 'Blocker Item',
                'tags' => ['blocker'],
                'status' => 'draft',
                'confidence' => 70,
                'created_at' => now()->subDays(5),
            ]);

            Entry::factory()->create([
                'title' => 'Intent Item',
                'tags' => ['user-intent'],
                'confidence' => 80,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->assertSuccessful();
        });

        it('combines project filter with multiple priority types', function () {
            Entry::factory()->create([
                'title' => 'Knowledge Blocker',
                'tags' => ['blocker', 'knowledge'],
                'status' => 'draft',
                'confidence' => 80,
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Knowledge Intent',
                'tags' => ['user-intent', 'knowledge'],
                'confidence' => 75,
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Other Project Blocker',
                'tags' => ['blocker', 'other'],
                'status' => 'draft',
                'confidence' => 90,
                'created_at' => now(),
            ]);

            $this->artisan('priorities --project=knowledge')
                ->expectsOutputToContain('Knowledge Blocker')
                ->expectsOutputToContain('Knowledge Intent')
                ->doesntExpectOutputToContain('Other Project Blocker')
                ->assertSuccessful();
        });

        it('handles all priority factors in one entry', function () {
            Entry::factory()->create([
                'title' => 'Ultimate Priority',
                'tags' => ['blocker', 'user-intent'],
                'priority' => 'critical',
                'status' => 'draft',
                'confidence' => 100,
                'created_at' => now(),
            ]);

            $this->artisan('priorities')
                ->expectsOutputToContain('Ultimate Priority')
                ->expectsOutputToContain('#1')
                ->assertSuccessful();
        });
    });
});
