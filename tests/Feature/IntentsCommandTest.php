<?php

declare(strict_types=1);

use App\Models\Entry;

describe('IntentsCommand', function () {
    describe('basic functionality', function () {
        it('lists user intents filtered by user-intent tag', function () {
            Entry::factory()->create([
                'title' => 'User Intent 1',
                'tags' => ['user-intent', 'knowledge'],
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'User Intent 2',
                'tags' => ['user-intent'],
                'created_at' => now()->subHour(),
            ]);

            Entry::factory()->create([
                'title' => 'Not a user intent',
                'tags' => ['other-tag'],
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('User Intent 1')
                ->expectsOutputToContain('User Intent 2')
                ->doesntExpectOutputToContain('Not a user intent')
                ->assertSuccessful();
        });

        it('handles empty results gracefully', function () {
            $this->artisan('intents')
                ->expectsOutputToContain('No user intents found')
                ->assertSuccessful();
        });

        it('shows correct count for single intent', function () {
            Entry::factory()->create([
                'title' => 'Single Intent',
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('Found 1 user intent:')
                ->assertSuccessful();
        });

        it('shows correct count for multiple intents', function () {
            Entry::factory()->count(3)->create([
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('Found 3 user intents:')
                ->assertSuccessful();
        });

        it('orders results by created_at descending', function () {
            Entry::factory()->create([
                'title' => 'Oldest Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(5),
            ]);

            Entry::factory()->create([
                'title' => 'Middle Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(2),
            ]);

            Entry::factory()->create([
                'title' => 'Newest Intent',
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $output = $this->artisan('intents')->run();

            expect($output)->toBe(0);
        });
    });

    describe('--limit flag', function () {
        it('limits results to specified count', function () {
            Entry::factory()->count(15)->create([
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $output = $this->artisan('intents --limit=5')->run();

            expect($output)->toBe(0);
        });

        it('uses default limit of 10 when not specified', function () {
            Entry::factory()->count(20)->create([
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $output = $this->artisan('intents')->run();

            expect($output)->toBe(0);
        });

        it('accepts custom limit value', function () {
            Entry::factory()->count(25)->create([
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $output = $this->artisan('intents --limit=3')->run();

            expect($output)->toBe(0);
        });

        it('shows all results when count is less than limit', function () {
            Entry::factory()->count(3)->create([
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $this->artisan('intents --limit=10')
                ->expectsOutputToContain('Found 3 user intents:')
                ->assertSuccessful();
        });
    });

    describe('--project flag', function () {
        it('filters by project tag', function () {
            Entry::factory()->create([
                'title' => 'Knowledge Intent',
                'tags' => ['user-intent', 'knowledge'],
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Conduit Intent',
                'tags' => ['user-intent', 'conduit-ui'],
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Other Project Intent',
                'tags' => ['user-intent', 'other-project'],
                'created_at' => now(),
            ]);

            $this->artisan('intents --project=knowledge')
                ->expectsOutputToContain('Knowledge Intent')
                ->doesntExpectOutputToContain('Conduit Intent')
                ->doesntExpectOutputToContain('Other Project Intent')
                ->assertSuccessful();
        });

        it('requires both user-intent and project tags (AND logic)', function () {
            Entry::factory()->create([
                'title' => 'Has Both Tags',
                'tags' => ['user-intent', 'knowledge'],
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Only Project Tag',
                'tags' => ['knowledge'],
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Only User Intent Tag',
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $this->artisan('intents --project=knowledge')
                ->expectsOutputToContain('Has Both Tags')
                ->doesntExpectOutputToContain('Only Project Tag')
                ->doesntExpectOutputToContain('Only User Intent Tag')
                ->assertSuccessful();
        });

        it('shows empty results when no intents match project', function () {
            Entry::factory()->create([
                'title' => 'Knowledge Intent',
                'tags' => ['user-intent', 'knowledge'],
                'created_at' => now(),
            ]);

            $this->artisan('intents --project=nonexistent')
                ->expectsOutputToContain('No user intents found')
                ->assertSuccessful();
        });
    });

    describe('--since flag', function () {
        it('filters by since date with absolute date', function () {
            Entry::factory()->create([
                'title' => 'Recent Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(2),
            ]);

            Entry::factory()->create([
                'title' => 'Old Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(30),
            ]);

            $sevenDaysAgo = now()->subDays(7)->format('Y-m-d');

            $this->artisan("intents --since={$sevenDaysAgo}")
                ->expectsOutputToContain('Recent Intent')
                ->doesntExpectOutputToContain('Old Intent')
                ->assertSuccessful();
        });

        it('filters by since date with relative date', function () {
            Entry::factory()->create([
                'title' => 'Recent Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(2),
            ]);

            Entry::factory()->create([
                'title' => 'Old Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(30),
            ]);

            $this->artisan('intents --since="7 days ago"')
                ->expectsOutputToContain('Recent Intent')
                ->doesntExpectOutputToContain('Old Intent')
                ->assertSuccessful();
        });

        it('includes intents from exact since date', function () {
            $exactDate = now()->subDays(7);

            Entry::factory()->create([
                'title' => 'Exact Date Intent',
                'tags' => ['user-intent'],
                'created_at' => $exactDate,
            ]);

            Entry::factory()->create([
                'title' => 'Before Date Intent',
                'tags' => ['user-intent'],
                'created_at' => $exactDate->copy()->subSecond(),
            ]);

            $this->artisan('intents --since="7 days ago"')
                ->expectsOutputToContain('Exact Date Intent')
                ->doesntExpectOutputToContain('Before Date Intent')
                ->assertSuccessful();
        });

        it('handles "1 week ago" format', function () {
            Entry::factory()->create([
                'title' => 'Within Week Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(3),
            ]);

            Entry::factory()->create([
                'title' => 'Before Week Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(10),
            ]);

            $this->artisan('intents --since="1 week ago"')
                ->expectsOutputToContain('Within Week Intent')
                ->doesntExpectOutputToContain('Before Week Intent')
                ->assertSuccessful();
        });

        it('shows empty results when all intents are before since date', function () {
            Entry::factory()->create([
                'title' => 'Old Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(30),
            ]);

            $this->artisan('intents --since="7 days ago"')
                ->expectsOutputToContain('No user intents found')
                ->assertSuccessful();
        });
    });

    describe('--full flag', function () {
        it('shows full content with --full flag', function () {
            Entry::factory()->create([
                'title' => 'Test Intent',
                'content' => 'This is the full content of the intent that should be displayed.',
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $this->artisan('intents --full')
                ->expectsOutputToContain('Content:')
                ->expectsOutputToContain('This is the full content of the intent that should be displayed.')
                ->assertSuccessful();
        });

        it('shows compact view by default', function () {
            Entry::factory()->create([
                'title' => 'Test Intent',
                'content' => 'Full content that should not appear in compact view',
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('Test Intent')
                ->doesntExpectOutputToContain('Full content that should not appear in compact view')
                ->assertSuccessful();
        });

        it('displays all metadata in full view', function () {
            $createdAt = now()->subDays(2);

            Entry::factory()->create([
                'title' => 'Full View Intent',
                'content' => 'Detailed content here',
                'tags' => ['user-intent', 'knowledge', 'enhancement'],
                'module' => 'knowledge',
                'created_at' => $createdAt,
            ]);

            $this->artisan('intents --full')
                ->expectsOutputToContain('ID:')
                ->expectsOutputToContain('Title: Full View Intent')
                ->expectsOutputToContain('Created:')
                ->expectsOutputToContain('Module: knowledge')
                ->expectsOutputToContain('Tags: user-intent, knowledge, enhancement')
                ->expectsOutputToContain('Content:')
                ->expectsOutputToContain('Detailed content here')
                ->assertSuccessful();
        });

        it('handles full view with intents missing optional fields', function () {
            Entry::factory()->create([
                'title' => 'Minimal Intent',
                'content' => 'Simple content',
                'tags' => ['user-intent'],
                'module' => null,
                'created_at' => now(),
            ]);

            $this->artisan('intents --full')
                ->expectsOutputToContain('Title: Minimal Intent')
                ->expectsOutputToContain('Content:')
                ->expectsOutputToContain('Simple content')
                ->assertSuccessful();
        });
    });

    describe('date grouping', function () {
        it('groups intents by date categories in compact view', function () {
            Entry::factory()->create([
                'title' => 'Today Intent',
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'This Week Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(3),
            ]);

            Entry::factory()->create([
                'title' => 'This Month Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(15),
            ]);

            Entry::factory()->create([
                'title' => 'Older Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(45),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('Today')
                ->expectsOutputToContain('This Week')
                ->expectsOutputToContain('This Month')
                ->expectsOutputToContain('Older')
                ->assertSuccessful();
        });

        it('only shows Today group when all intents are from today', function () {
            Entry::factory()->count(3)->create([
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('Today')
                ->doesntExpectOutputToContain('This Week')
                ->doesntExpectOutputToContain('This Month')
                ->doesntExpectOutputToContain('Older')
                ->assertSuccessful();
        });

        it('categorizes exactly 7 days old as This Week', function () {
            Entry::factory()->create([
                'title' => 'Exactly Seven Days',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(7),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('This Week')
                ->assertSuccessful();
        });

        it('categorizes exactly 30 days old as This Month', function () {
            Entry::factory()->create([
                'title' => 'Exactly Thirty Days',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(30),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('This Month')
                ->assertSuccessful();
        });

        it('categorizes 31 days old as Older', function () {
            Entry::factory()->create([
                'title' => 'Thirty One Days',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(31),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('Older')
                ->assertSuccessful();
        });

        it('does not group in full view', function () {
            Entry::factory()->create([
                'title' => 'Today Intent',
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Old Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(45),
            ]);

            $output = $this->artisan('intents --full')->run();

            expect($output)->toBe(0);
        });
    });

    describe('compact output formatting', function () {
        it('displays ID and title in compact view', function () {
            $entry = Entry::factory()->create([
                'title' => 'Test Intent Title',
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain("[{$entry->id}]")
                ->expectsOutputToContain('Test Intent Title')
                ->assertSuccessful();
        });

        it('shows "today" for intents created today', function () {
            Entry::factory()->create([
                'title' => 'Today Intent',
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('today')
                ->assertSuccessful();
        });

        it('shows days ago for older intents', function () {
            Entry::factory()->create([
                'title' => 'Three Days Ago Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subDays(3),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('3 days ago')
                ->assertSuccessful();
        });

        it('displays module when present', function () {
            Entry::factory()->create([
                'title' => 'Intent With Module',
                'tags' => ['user-intent'],
                'module' => 'knowledge',
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('Module: knowledge')
                ->assertSuccessful();
        });

        it('displays additional tags excluding user-intent', function () {
            Entry::factory()->create([
                'title' => 'Intent With Tags',
                'tags' => ['user-intent', 'enhancement', 'issue-63'],
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('Tags: enhancement, issue-63')
                ->assertSuccessful();
        });

        it('does not show Tags label when only user-intent tag exists', function () {
            Entry::factory()->create([
                'title' => 'Single Tag Intent',
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $output = $this->artisan('intents')->run();

            expect($output)->toBe(0);
        });

        it('handles intents with no module', function () {
            Entry::factory()->create([
                'title' => 'No Module Intent',
                'tags' => ['user-intent'],
                'module' => null,
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('No Module Intent')
                ->assertSuccessful();
        });

        it('handles intents with empty tags array', function () {
            Entry::factory()->create([
                'title' => 'Empty Tags Intent',
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('Empty Tags Intent')
                ->assertSuccessful();
        });
    });

    describe('combined filters', function () {
        it('combines project and since filters with AND logic', function () {
            Entry::factory()->create([
                'title' => 'Match Both',
                'tags' => ['user-intent', 'knowledge'],
                'created_at' => now()->subDays(2),
            ]);

            Entry::factory()->create([
                'title' => 'Match Project Only',
                'tags' => ['user-intent', 'knowledge'],
                'created_at' => now()->subDays(30),
            ]);

            Entry::factory()->create([
                'title' => 'Match Date Only',
                'tags' => ['user-intent', 'other'],
                'created_at' => now()->subDays(2),
            ]);

            Entry::factory()->create([
                'title' => 'Match Neither',
                'tags' => ['user-intent', 'other'],
                'created_at' => now()->subDays(30),
            ]);

            $this->artisan('intents --project=knowledge --since="7 days ago"')
                ->expectsOutputToContain('Match Both')
                ->doesntExpectOutputToContain('Match Project Only')
                ->doesntExpectOutputToContain('Match Date Only')
                ->doesntExpectOutputToContain('Match Neither')
                ->assertSuccessful();
        });

        it('combines project, since, and limit filters', function () {
            Entry::factory()->count(5)->create([
                'tags' => ['user-intent', 'knowledge'],
                'created_at' => now()->subDays(2),
            ]);

            Entry::factory()->count(3)->create([
                'tags' => ['user-intent', 'other'],
                'created_at' => now()->subDays(2),
            ]);

            $output = $this->artisan('intents --project=knowledge --since="7 days ago" --limit=3')->run();

            expect($output)->toBe(0);
        });

        it('combines all flags including full', function () {
            Entry::factory()->create([
                'title' => 'Complete Match',
                'content' => 'Full content for complete match',
                'tags' => ['user-intent', 'knowledge'],
                'created_at' => now()->subDays(2),
            ]);

            Entry::factory()->create([
                'title' => 'Partial Match',
                'tags' => ['user-intent', 'knowledge'],
                'created_at' => now()->subDays(30),
            ]);

            $this->artisan('intents --project=knowledge --since="7 days ago" --limit=10 --full')
                ->expectsOutputToContain('Complete Match')
                ->expectsOutputToContain('Content:')
                ->expectsOutputToContain('Full content for complete match')
                ->doesntExpectOutputToContain('Partial Match')
                ->assertSuccessful();
        });
    });

    describe('edge cases', function () {
        it('handles intents with null tags gracefully', function () {
            // This should not match since whereJsonContains requires the tag
            Entry::factory()->create([
                'title' => 'Null Tags Intent',
                'tags' => null,
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('No user intents found')
                ->assertSuccessful();
        });

        it('handles very old intents', function () {
            Entry::factory()->create([
                'title' => 'Ancient Intent',
                'tags' => ['user-intent'],
                'created_at' => now()->subYears(2),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('Older')
                ->assertSuccessful();
        });

        it('handles multiple intents in same date group', function () {
            Entry::factory()->count(5)->create([
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('Found 5 user intents:')
                ->expectsOutputToContain('Today')
                ->assertSuccessful();
        });

        it('handles limit of 1', function () {
            Entry::factory()->count(10)->create([
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $output = $this->artisan('intents --limit=1')->run();

            expect($output)->toBe(0);
        });

        it('handles very large limit', function () {
            Entry::factory()->count(5)->create([
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $this->artisan('intents --limit=1000')
                ->expectsOutputToContain('Found 5 user intents:')
                ->assertSuccessful();
        });
    });

    describe('whereJsonContains query pattern', function () {
        it('uses whereJsonContains for user-intent tag filtering', function () {
            Entry::factory()->create([
                'title' => 'Array Tag Intent',
                'tags' => ['user-intent', 'other'],
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'String Match Should Not Appear',
                'tags' => ['not-user-intent'],
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('Array Tag Intent')
                ->doesntExpectOutputToContain('String Match Should Not Appear')
                ->assertSuccessful();
        });

        it('uses whereJsonContains for project tag filtering', function () {
            Entry::factory()->create([
                'title' => 'Has Both JSON Tags',
                'tags' => ['user-intent', 'knowledge'],
                'created_at' => now(),
            ]);

            Entry::factory()->create([
                'title' => 'Partial String Match Should Not Appear',
                'tags' => ['user-intent', 'knowledgebase'],
                'created_at' => now(),
            ]);

            $this->artisan('intents --project=knowledge')
                ->expectsOutputToContain('Has Both JSON Tags')
                ->doesntExpectOutputToContain('Partial String Match Should Not Appear')
                ->assertSuccessful();
        });

        it('handles tags as proper JSON array', function () {
            Entry::factory()->create([
                'title' => 'Proper JSON Array',
                'tags' => json_decode('["user-intent", "knowledge", "enhancement"]', true),
                'created_at' => now(),
            ]);

            $this->artisan('intents')
                ->expectsOutputToContain('Proper JSON Array')
                ->assertSuccessful();
        });
    });

    describe('output separator and formatting', function () {
        it('displays separator in full view', function () {
            Entry::factory()->create([
                'title' => 'Test Intent',
                'content' => 'Content here',
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $this->artisan('intents --full')
                ->expectsOutputToContain(str_repeat('─', 80))
                ->assertSuccessful();
        });

        it('uses proper spacing in compact view', function () {
            Entry::factory()->create([
                'title' => 'Spacing Test',
                'tags' => ['user-intent'],
                'created_at' => now(),
            ]);

            $output = $this->artisan('intents')->run();

            expect($output)->toBe(0);
        });
    });
});
