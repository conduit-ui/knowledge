<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;

describe('knowledge:conflicts command', function (): void {
    it('reports no conflicts when there are none', function (): void {
        Entry::factory()->count(3)->create([
            'priority' => 'medium', // Same priority, won't conflict
            'status' => 'validated',
        ]);

        $this->artisan('conflicts')
            ->expectsOutput('No conflicts found.')
            ->assertSuccessful();
    });

    it('detects explicit conflicts with conflicts_with relationships', function (): void {
        $entry1 = Entry::factory()->create(['title' => 'Entry One', 'status' => 'validated']);
        $entry2 = Entry::factory()->create(['title' => 'Entry Two', 'status' => 'validated']);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => Relationship::TYPE_CONFLICTS_WITH,
        ]);

        $this->artisan('conflicts')
            ->expectsOutputToContain('Found 1 explicit conflict')
            ->expectsOutputToContain('Entry One')
            ->expectsOutputToContain('Entry Two')
            ->expectsOutputToContain('conflicts with')
            ->assertSuccessful();
    });

    it('detects multiple explicit conflicts', function (): void {
        $entry1 = Entry::factory()->create(['title' => 'Entry One', 'status' => 'validated']);
        $entry2 = Entry::factory()->create(['title' => 'Entry Two', 'status' => 'validated']);
        $entry3 = Entry::factory()->create(['title' => 'Entry Three', 'status' => 'validated']);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => Relationship::TYPE_CONFLICTS_WITH,
        ]);

        Relationship::factory()->create([
            'from_entry_id' => $entry2->id,
            'to_entry_id' => $entry3->id,
            'type' => Relationship::TYPE_CONFLICTS_WITH,
        ]);

        $this->artisan('conflicts')
            ->expectsOutputToContain('Found 2 explicit conflicts')
            ->assertSuccessful();
    });

    it('detects potential conflicts with same category and conflicting priorities', function (): void {
        $critical = Entry::factory()->create([
            'title' => 'Critical Security Entry',
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'critical',
            'status' => 'validated',
            'tags' => ['authentication', 'security'],
        ]);

        $low = Entry::factory()->create([
            'title' => 'Low Priority Auth Entry',
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'low',
            'status' => 'validated',
            'tags' => ['authentication', 'security'],
        ]);

        $result = $this->artisan('conflicts');

        $result->expectsOutputToContain('potential conflict')
            ->expectsOutputToContain('Critical Security Entry')
            ->expectsOutputToContain('Low Priority Auth Entry')
            ->expectsOutputToContain('Same category/module with conflicting priorities')
            ->assertSuccessful();
    });

    it('detects potential conflicts based on title word overlap', function (): void {
        $critical = Entry::factory()->create([
            'title' => 'Database Connection Pool Settings',
            'category' => 'architecture',
            'module' => 'database',
            'priority' => 'critical',
            'status' => 'validated',
            'tags' => [],
        ]);

        $low = Entry::factory()->create([
            'title' => 'Database Connection Configuration',
            'category' => 'architecture',
            'module' => 'database',
            'priority' => 'low',
            'status' => 'validated',
            'tags' => [],
        ]);

        $result = $this->artisan('conflicts');

        $result->expectsOutputToContain('potential conflict')
            ->expectsOutputToContain('Database Connection Pool Settings')
            ->expectsOutputToContain('Database Connection Configuration')
            ->assertSuccessful();
    });

    it('filters conflicts by category option', function (): void {
        $security1 = Entry::factory()->create([
            'title' => 'Security Auth Module',
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'critical',
            'status' => 'validated',
            'tags' => ['security', 'authentication'],
        ]);

        $security2 = Entry::factory()->create([
            'title' => 'Security Auth Implementation',
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'low',
            'status' => 'validated',
            'tags' => ['security', 'authentication'],
        ]);

        $arch1 = Entry::factory()->create([
            'title' => 'Database Architecture Design',
            'category' => 'architecture',
            'module' => 'db',
            'priority' => 'critical',
            'status' => 'validated',
            'tags' => ['database', 'architecture'],
        ]);

        $arch2 = Entry::factory()->create([
            'title' => 'Database Architecture Pattern',
            'category' => 'architecture',
            'module' => 'db',
            'priority' => 'low',
            'status' => 'validated',
            'tags' => ['database', 'architecture'],
        ]);

        $result = $this->artisan('conflicts', ['--category' => 'security']);

        // Should find security conflicts but not architecture ones
        $result->expectsOutputToContain('potential conflict')
            ->expectsOutputToContain('Security Auth Module')
            ->doesntExpectOutputToContain('Database Architecture')
            ->assertSuccessful();
    });

    it('filters conflicts by module option', function (): void {
        $auth1 = Entry::factory()->create([
            'title' => 'Authentication Module Implementation Strategy',
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'critical',
            'status' => 'validated',
            'tags' => ['authentication', 'implementation'],
        ]);

        $auth2 = Entry::factory()->create([
            'title' => 'Authentication Module Implementation Design',
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'low',
            'status' => 'validated',
            'tags' => ['authentication', 'implementation'],
        ]);

        $billing1 = Entry::factory()->create([
            'title' => 'Billing System Core Logic',
            'category' => 'business',
            'module' => 'billing',
            'priority' => 'critical',
            'status' => 'validated',
            'tags' => ['billing', 'system'],
        ]);

        $billing2 = Entry::factory()->create([
            'title' => 'Billing System Integration',
            'category' => 'business',
            'module' => 'billing',
            'priority' => 'low',
            'status' => 'validated',
            'tags' => ['billing', 'system'],
        ]);

        $result = $this->artisan('conflicts', ['--module' => 'auth']);

        // Should find auth conflicts but not billing ones
        $result->expectsOutputToContain('potential conflict')
            ->expectsOutputToContain('Authentication Module Implementation')
            ->doesntExpectOutputToContain('Billing System')
            ->assertSuccessful();
    });

    it('skips deprecated entries when finding potential conflicts', function (): void {
        $active = Entry::factory()->create([
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'critical',
            'status' => 'validated',
            'tags' => ['security', 'auth'],
        ]);

        $deprecated = Entry::factory()->create([
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'low',
            'status' => 'deprecated',
            'tags' => ['security', 'auth'],
        ]);

        $this->artisan('conflicts')
            ->expectsOutput('No conflicts found.')
            ->assertSuccessful();
    });

    it('does not report potential conflicts if explicit conflict relationship exists', function (): void {
        $entry1 = Entry::factory()->create([
            'title' => 'Entry One',
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'critical',
            'status' => 'validated',
            'tags' => ['security', 'auth'],
        ]);

        $entry2 = Entry::factory()->create([
            'title' => 'Entry Two',
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'low',
            'status' => 'validated',
            'tags' => ['security', 'auth'],
        ]);

        // Create explicit conflict relationship
        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => Relationship::TYPE_CONFLICTS_WITH,
        ]);

        $this->artisan('conflicts')
            ->expectsOutputToContain('Found 1 explicit conflict')
            ->expectsOutputToContain('Entry One')
            ->doesntExpectOutputToContain('potential')
            ->assertSuccessful();
    });

    it('requires at least 2 common tags for topic overlap detection', function (): void {
        $entry1 = Entry::factory()->create([
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'critical',
            'status' => 'validated',
            'tags' => ['security'], // Only 1 common tag
        ]);

        $entry2 = Entry::factory()->create([
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'low',
            'status' => 'validated',
            'tags' => ['security', 'different'], // Only 1 common tag
        ]);

        $this->artisan('conflicts')
            ->expectsOutput('No conflicts found.')
            ->assertSuccessful();
    });

    it('provides resolution suggestions in output', function (): void {
        $entry1 = Entry::factory()->create(['status' => 'validated']);
        $entry2 = Entry::factory()->create(['status' => 'validated']);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => Relationship::TYPE_CONFLICTS_WITH,
        ]);

        $this->artisan('conflicts')
            ->expectsOutputToContain('Resolve conflicts by:')
            ->expectsOutputToContain('knowledge:deprecate')
            ->expectsOutputToContain('knowledge:merge')
            ->assertSuccessful();
    });

    it('handles entries with no category or module gracefully', function (): void {
        $entry1 = Entry::factory()->create([
            'title' => 'Shared Common Words Here',
            'category' => null,
            'module' => null,
            'priority' => 'critical',
            'status' => 'validated',
            'tags' => ['tag1', 'tag2'],
        ]);

        $entry2 = Entry::factory()->create([
            'title' => 'Shared Common Terms Here',
            'category' => null,
            'module' => null,
            'priority' => 'low',
            'status' => 'validated',
            'tags' => ['tag1', 'tag2'],
        ]);

        $result = $this->artisan('conflicts');

        $result->expectsOutputToContain('potential conflict')
            ->assertSuccessful();
    });

    it('ignores entries with same priority level', function (): void {
        $entry1 = Entry::factory()->create([
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'critical',
            'status' => 'validated',
            'tags' => ['security', 'auth'],
        ]);

        $entry2 = Entry::factory()->create([
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'critical', // Same priority, not conflicting
            'status' => 'validated',
            'tags' => ['security', 'auth'],
        ]);

        $this->artisan('conflicts')
            ->expectsOutput('No conflicts found.')
            ->assertSuccessful();
    });

    it('filters title words by length when detecting overlap', function (): void {
        // Words 3 chars or less should be ignored
        $entry1 = Entry::factory()->create([
            'title' => 'Testing Authentication Module Implementation',
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'critical',
            'status' => 'validated',
            'tags' => [],
        ]);

        $entry2 = Entry::factory()->create([
            'title' => 'Testing Authentication System Implementation',
            'category' => 'security',
            'module' => 'auth',
            'priority' => 'low',
            'status' => 'validated',
            'tags' => [],
        ]);

        // "testing", "authentication", and "implementation" all > 3 chars - should find conflict
        $this->artisan('conflicts')
            ->expectsOutputToContain('potential conflict')
            ->assertSuccessful();
    });
});
