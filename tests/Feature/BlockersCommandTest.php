<?php

declare(strict_types=1);

use App\Models\Entry;

describe('BlockersCommand', function () {
    it('shows active blockers by default', function () {
        Entry::factory()->create([
            'title' => 'Active Blocker',
            'tags' => ['blocker'],
            'status' => 'draft',
            'created_at' => now()->subDays(3),
        ]);

        Entry::factory()->create([
            'title' => 'Resolved Blocker',
            'tags' => ['blocker'],
            'status' => 'validated',
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('blockers')
            ->expectsOutputToContain('Active Blocker')
            ->doesntExpectOutputToContain('Resolved Blocker')
            ->assertSuccessful();
    });

    it('shows blocker age in days', function () {
        Entry::factory()->create([
            'title' => 'Old Blocker',
            'tags' => ['blocker'],
            'status' => 'draft',
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('blockers')
            ->expectsOutputToContain('5 days')
            ->assertSuccessful();
    });

    it('highlights long-standing blockers over 7 days', function () {
        Entry::factory()->create([
            'title' => 'Long Standing Blocker',
            'tags' => ['blocker'],
            'status' => 'draft',
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('blockers')
            ->expectsOutputToContain('Long Standing Blocker')
            ->assertSuccessful();
    });

    it('shows resolved blockers with --resolved flag', function () {
        Entry::factory()->create([
            'title' => 'Resolved Blocker',
            'tags' => ['blocker'],
            'status' => 'validated',
            'created_at' => now()->subDays(5),
        ]);

        Entry::factory()->create([
            'title' => 'Active Blocker',
            'tags' => ['blocker'],
            'status' => 'draft',
            'created_at' => now()->subDays(3),
        ]);

        $this->artisan('blockers --resolved')
            ->expectsOutputToContain('Resolved Blocker')
            ->doesntExpectOutputToContain('Active Blocker')
            ->assertSuccessful();
    });

    it('shows resolution patterns when available', function () {
        Entry::factory()->create([
            'title' => 'Resolved Blocker with Pattern',
            'content' => "## Blockers Resolved\n- Issue: Found solution by checking config\n- Pattern: Always check configuration files first",
            'tags' => ['blocker'],
            'status' => 'validated',
        ]);

        $this->artisan('blockers --resolved')
            ->expectsOutputToContain('Pattern:')
            ->assertSuccessful();
    });

    it('supports --project flag to filter by project', function () {
        Entry::factory()->create([
            'title' => 'Knowledge Blocker',
            'tags' => ['blocker', 'knowledge'],
            'status' => 'draft',
        ]);

        Entry::factory()->create([
            'title' => 'Other Project Blocker',
            'tags' => ['blocker', 'other-project'],
            'status' => 'draft',
        ]);

        $this->artisan('blockers --project=knowledge')
            ->expectsOutputToContain('Knowledge Blocker')
            ->doesntExpectOutputToContain('Other Project Blocker')
            ->assertSuccessful();
    });

    it('groups blockers by age category', function () {
        Entry::factory()->create([
            'title' => 'Today Blocker',
            'tags' => ['blocker'],
            'status' => 'draft',
            'created_at' => now(),
        ]);

        Entry::factory()->create([
            'title' => 'This Week Blocker',
            'tags' => ['blocker'],
            'status' => 'draft',
            'created_at' => now()->subDays(3),
        ]);

        Entry::factory()->create([
            'title' => 'Old Blocker',
            'tags' => ['blocker'],
            'status' => 'draft',
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('blockers')
            ->expectsOutputToContain('Today')
            ->expectsOutputToContain('This Week')
            ->expectsOutputToContain('>1 Week')
            ->assertSuccessful();
    });

    it('shows no blockers message when none exist', function () {
        $this->artisan('blockers')
            ->expectsOutputToContain('No blockers')
            ->assertSuccessful();
    });

    it('identifies blockers from content with ## Blockers section', function () {
        Entry::factory()->create([
            'title' => 'Entry with Blockers Section',
            'content' => "## Blockers\n- Database connection issues\n- API rate limits",
            'status' => 'draft',
        ]);

        $this->artisan('blockers')
            ->expectsOutputToContain('Entry with Blockers Section')
            ->assertSuccessful();
    });

    it('identifies blockers from content with Blocker: prefix', function () {
        Entry::factory()->create([
            'title' => 'Entry with Blocker Prefix',
            'content' => 'Blocker: Cannot access production database',
            'status' => 'draft',
        ]);

        $this->artisan('blockers')
            ->expectsOutputToContain('Entry with Blocker Prefix')
            ->assertSuccessful();
    });

    it('identifies blockers with blocked tag', function () {
        Entry::factory()->create([
            'title' => 'Blocked Entry',
            'tags' => ['blocked'],
            'status' => 'draft',
        ]);

        $this->artisan('blockers')
            ->expectsOutputToContain('Blocked Entry')
            ->assertSuccessful();
    });

    it('extracts resolution patterns from blockers resolved section', function () {
        Entry::factory()->create([
            'title' => 'Resolved with Pattern',
            'content' => "## Blockers Resolved\n- API Token Discovery: After 10+ failed attempts, found PREFRONTAL_API_TOKEN in ~/.zshrc\n- Pattern: Check environment variables and shell configs FIRST",
            'tags' => ['blocker'],
            'status' => 'validated',
        ]);

        $this->artisan('blockers --resolved')
            ->expectsOutputToContain('Check environment variables')
            ->assertSuccessful();
    });

    it('displays blocker details including title and status', function () {
        $blocker = Entry::factory()->create([
            'title' => 'Detailed Blocker',
            'tags' => ['blocker'],
            'status' => 'draft',
            'category' => 'infrastructure',
        ]);

        $this->artisan('blockers')
            ->expectsOutputToContain("ID: {$blocker->id}")
            ->expectsOutputToContain('Detailed Blocker')
            ->expectsOutputToContain('Status: draft')
            ->assertSuccessful();
    });

    it('shows category when present', function () {
        Entry::factory()->create([
            'title' => 'Categorized Blocker',
            'tags' => ['blocker'],
            'status' => 'draft',
            'category' => 'deployment',
        ]);

        $this->artisan('blockers')
            ->expectsOutputToContain('Category: deployment')
            ->assertSuccessful();
    });

    it('filters by project using module field', function () {
        Entry::factory()->create([
            'title' => 'Module Based Blocker',
            'tags' => ['blocker'],
            'module' => 'knowledge',
            'status' => 'draft',
        ]);

        Entry::factory()->create([
            'title' => 'Other Module Blocker',
            'tags' => ['blocker'],
            'module' => 'other',
            'status' => 'draft',
        ]);

        $this->artisan('blockers --project=knowledge')
            ->expectsOutputToContain('Module Based Blocker')
            ->doesntExpectOutputToContain('Other Module Blocker')
            ->assertSuccessful();
    });

    it('shows count of blockers found', function () {
        Entry::factory()->count(3)->create([
            'tags' => ['blocker'],
            'status' => 'draft',
        ]);

        $this->artisan('blockers')
            ->expectsOutputToContain('Found 3')
            ->assertSuccessful();
    });

    it('handles blockers with 0 days age', function () {
        Entry::factory()->create([
            'title' => 'New Blocker',
            'tags' => ['blocker'],
            'status' => 'draft',
            'created_at' => now(),
        ]);

        $this->artisan('blockers')
            ->expectsOutputToContain('0 days')
            ->assertSuccessful();
    });

    it('extracts multiple patterns from resolved blockers', function () {
        Entry::factory()->create([
            'title' => 'Multi Pattern Resolution',
            'content' => "## Blockers Resolved\n- First Issue: Solution here\n- Pattern: First pattern\n- Second Issue: Another solution\n- Pattern: Second pattern",
            'tags' => ['blocker'],
            'status' => 'validated',
        ]);

        $this->artisan('blockers --resolved')
            ->expectsOutputToContain('First pattern')
            ->assertSuccessful();
    });

    it('extracts descriptions when no explicit patterns exist', function () {
        Entry::factory()->create([
            'title' => 'Resolved without explicit pattern',
            'content' => "## Blockers Resolved\n- Database connection issues: Fixed by updating credentials\n- API rate limit: Solved by implementing caching",
            'tags' => ['blocker'],
            'status' => 'validated',
        ]);

        $this->artisan('blockers --resolved')
            ->expectsOutputToContain('Database connection issues')
            ->expectsOutputToContain('API rate limit')
            ->assertSuccessful();
    });
});
