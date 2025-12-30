<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;

describe('knowledge:prune command', function (): void {
    it('reports no entries when none match criteria', function (): void {
        Entry::factory()->create();

        $this->artisan('prune', ['--dry-run' => true])
            ->expectsOutput('No entries found matching the criteria.')
            ->assertSuccessful();
    });

    it('finds and displays old entries with dry-run flag', function (): void {
        // Create old entry
        $old = Entry::factory()->create([
            'title' => 'Old Entry',
            'created_at' => now()->subYears(2),
        ]);

        // Create new entry
        Entry::factory()->create([
            'title' => 'New Entry',
            'created_at' => now(),
        ]);

        $this->artisan('prune', ['--dry-run' => true])
            ->expectsOutputToContain('Found 1 entry older than')
            ->expectsOutputToContain('Old Entry')
            ->expectsOutputToContain('Dry run - no changes made')
            ->assertSuccessful();

        // Verify no entries were deleted
        expect(Entry::count())->toBe(2);
    });

    it('permanently deletes old entries when confirmed', function (): void {
        $old = Entry::factory()->create([
            'title' => 'Old Entry',
            'created_at' => now()->subYears(2),
        ]);

        $this->artisan('prune', ['--force' => true])
            ->expectsOutputToContain('Pruned 1 entry')
            ->assertSuccessful();

        expect(Entry::count())->toBe(0);
    });

    it('deletes relationships when pruning entries', function (): void {
        $old = Entry::factory()->create(['created_at' => now()->subYears(2)]);
        $new = Entry::factory()->create(['created_at' => now()]);

        // Create relationships involving the old entry
        Relationship::factory()->create([
            'from_entry_id' => $old->id,
            'to_entry_id' => $new->id,
        ]);

        Relationship::factory()->create([
            'from_entry_id' => $new->id,
            'to_entry_id' => $old->id,
        ]);

        $this->artisan('prune', ['--force' => true])
            ->assertSuccessful();

        // Old entry should be deleted
        expect(Entry::count())->toBe(1);

        // Relationships should be deleted
        expect(Relationship::count())->toBe(0);
    });

    it('filters by deprecated-only flag', function (): void {
        // Old deprecated entry
        $oldDeprecated = Entry::factory()->create([
            'status' => 'deprecated',
            'created_at' => now()->subYears(2),
        ]);

        // Old active entry
        $oldActive = Entry::factory()->create([
            'status' => 'validated',
            'created_at' => now()->subYears(2),
        ]);

        $this->artisan('prune', [
            '--deprecated-only' => true,
            '--force' => true,
        ])
            ->expectsOutputToContain('Pruned 1 entry')
            ->assertSuccessful();

        // Only deprecated should be deleted
        expect(Entry::count())->toBe(1);
        expect(Entry::first()->status)->toBe('validated');
    });

    it('parses days threshold correctly', function (): void {
        $old = Entry::factory()->create(['created_at' => now()->subDays(35)]);
        $recent = Entry::factory()->create(['created_at' => now()->subDays(25)]);

        $this->artisan('prune', [
            '--older-than' => '30d',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Found 1 entry')
            ->assertSuccessful();
    });

    it('parses months threshold correctly', function (): void {
        $old = Entry::factory()->create(['created_at' => now()->subMonths(7)]);
        $recent = Entry::factory()->create(['created_at' => now()->subMonths(5)]);

        $this->artisan('prune', [
            '--older-than' => '6m',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Found 1 entry')
            ->assertSuccessful();
    });

    it('parses years threshold correctly', function (): void {
        $old = Entry::factory()->create(['created_at' => now()->subYears(2)]);
        $recent = Entry::factory()->create(['created_at' => now()->subMonths(6)]);

        $this->artisan('prune', [
            '--older-than' => '1y',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Found 1 entry')
            ->assertSuccessful();
    });

    it('fails with invalid threshold format', function (): void {
        $this->artisan('prune', [
            '--older-than' => 'invalid',
        ])
            ->expectsOutputToContain('Invalid threshold format')
            ->assertFailed();
    });

    it('displays entry statistics by status', function (): void {
        Entry::factory()->create([
            'status' => 'deprecated',
            'created_at' => now()->subYears(2),
        ]);

        Entry::factory()->create([
            'status' => 'validated',
            'created_at' => now()->subYears(2),
        ]);

        Entry::factory()->create([
            'status' => 'deprecated',
            'created_at' => now()->subYears(2),
        ]);

        $this->artisan('prune', ['--dry-run' => true])
            ->expectsOutputToContain('deprecated: 2')
            ->expectsOutputToContain('validated: 1')
            ->assertSuccessful();
    });

    it('shows sample of entries to be deleted', function (): void {
        // Create more than 5 old entries
        foreach (range(1, 7) as $i) {
            Entry::factory()->create([
                'title' => "Entry {$i}",
                'created_at' => now()->subYears(2),
            ]);
        }

        $this->artisan('prune', ['--dry-run' => true])
            ->expectsOutputToContain('Entry 1')
            ->expectsOutputToContain('... and 2 more')
            ->assertSuccessful();
    });

    it('requires confirmation without force flag', function (): void {
        $old = Entry::factory()->create(['created_at' => now()->subYears(2)]);

        // Simulate user declining confirmation
        $this->artisan('prune')
            ->expectsQuestion('Are you sure you want to permanently delete these entries?', false)
            ->expectsOutput('Operation cancelled.')
            ->assertSuccessful();

        // Entry should not be deleted
        expect(Entry::count())->toBe(1);
    });

    it('skips confirmation with force flag', function (): void {
        $old = Entry::factory()->create(['created_at' => now()->subYears(2)]);

        $this->artisan('prune', ['--force' => true])
            ->assertSuccessful();

        // Entry should be deleted without prompt
        expect(Entry::count())->toBe(0);
    });

    it('displays how long ago entries were created', function (): void {
        Entry::factory()->create([
            'title' => 'Very Old Entry',
            'created_at' => now()->subYears(3),
        ]);

        $this->artisan('prune', ['--dry-run' => true])
            ->expectsOutputToContain('created')
            ->assertSuccessful();
    });

    it('deletes multiple entries and counts correctly', function (): void {
        Entry::factory()->count(5)->create(['created_at' => now()->subYears(2)]);

        $this->artisan('prune', ['--force' => true])
            ->expectsOutputToContain('Pruned 5 entries')
            ->assertSuccessful();

        expect(Entry::count())->toBe(0);
    });

    it('uses singular form for single entry', function (): void {
        Entry::factory()->create(['created_at' => now()->subYears(2)]);

        $this->artisan('prune', ['--force' => true])
            ->expectsOutputToContain('Pruned 1 entry')
            ->assertSuccessful();
    });

    it('combines deprecated-only and custom threshold', function (): void {
        // Old deprecated
        Entry::factory()->create([
            'status' => 'deprecated',
            'created_at' => now()->subMonths(7),
        ]);

        // Old active
        Entry::factory()->create([
            'status' => 'validated',
            'created_at' => now()->subMonths(7),
        ]);

        // Recent deprecated
        Entry::factory()->create([
            'status' => 'deprecated',
            'created_at' => now()->subMonths(5),
        ]);

        $this->artisan('prune', [
            '--older-than' => '6m',
            '--deprecated-only' => true,
            '--force' => true,
        ])
            ->expectsOutputToContain('Pruned 1 entry')
            ->assertSuccessful();

        expect(Entry::count())->toBe(2);
    });

    it('handles entries with both incoming and outgoing relationships', function (): void {
        $old = Entry::factory()->create(['created_at' => now()->subYears(2)]);
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();

        // Outgoing
        Relationship::factory()->create([
            'from_entry_id' => $old->id,
            'to_entry_id' => $entry1->id,
        ]);

        // Incoming
        Relationship::factory()->create([
            'from_entry_id' => $entry2->id,
            'to_entry_id' => $old->id,
        ]);

        $this->artisan('prune', ['--force' => true])
            ->assertSuccessful();

        // All relationships involving old entry should be deleted
        expect(Relationship::count())->toBe(0);
        expect(Entry::count())->toBe(2);
    });

    it('defaults to 1 year threshold when not specified', function (): void {
        $veryOld = Entry::factory()->create(['created_at' => now()->subYears(2)]);
        $recent = Entry::factory()->create(['created_at' => now()->subMonths(6)]);

        $this->artisan('prune', ['--dry-run' => true])
            ->expectsOutputToContain('Found 1 entry')
            ->assertSuccessful();
    });
});
