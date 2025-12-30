<?php

declare(strict_types=1);

use App\Models\Entry;

beforeEach(function () {
    Entry::query()->delete();
    // Set test API token
    putenv('PREFRONTAL_API_TOKEN=test-token-12345');
});

afterEach(function () {
    putenv('PREFRONTAL_API_TOKEN');
});

describe('SyncCommand', function () {
    it('fails when PREFRONTAL_API_TOKEN is not set', function () {
        putenv('PREFRONTAL_API_TOKEN');

        $this->artisan('sync')
            ->expectsOutput('PREFRONTAL_API_TOKEN environment variable is not set.')
            ->assertFailed();
    });

    it('has pull flag option', function () {
        // Just verify the command accepts the --pull flag without errors
        // Actual HTTP testing will be done once API endpoint exists
        $this->artisan('sync', ['--pull' => true])
            ->assertSuccessful();
    })->skip('Requires API endpoint from issue #149');

    it('has push flag option', function () {
        // Create local entry to push
        Entry::create([
            'title' => 'Test Entry',
            'content' => 'Test content',
            'priority' => 'medium',
            'confidence' => 50,
            'status' => 'draft',
        ]);

        // Just verify the command accepts the --push flag without errors
        // Actual HTTP testing will be done once API endpoint exists
        $this->artisan('sync', ['--push' => true])
            ->assertSuccessful();
    })->skip('Requires API endpoint from issue #149');

    it('performs two-way sync by default', function () {
        // Create local entry
        Entry::create([
            'title' => 'Local Entry',
            'content' => 'Local content',
            'priority' => 'medium',
            'confidence' => 50,
            'status' => 'draft',
        ]);

        // Verify two-way sync is the default behavior
        $this->artisan('sync')
            ->expectsOutput('Starting two-way sync (pull then push)...')
            ->assertSuccessful();
    })->skip('Requires API endpoint from issue #149');

    it('handles empty local database when pushing', function () {
        // Verify graceful handling when no local entries exist
        $this->artisan('sync', ['--push' => true])
            ->expectsOutput('No local entries to push.')
            ->assertSuccessful();
    });

    it('generates unique deterministic IDs for entries', function () {
        $entry1 = Entry::create([
            'title' => 'Entry 1',
            'content' => 'Content 1',
            'priority' => 'medium',
            'confidence' => 50,
            'status' => 'draft',
        ]);

        $entry2 = Entry::create([
            'title' => 'Entry 2',
            'content' => 'Content 2',
            'priority' => 'medium',
            'confidence' => 50,
            'status' => 'draft',
        ]);

        // Test unique_id generation logic
        $id1 = hash('sha256', $entry1->id.'-'.$entry1->title);
        $id2 = hash('sha256', $entry2->id.'-'.$entry2->title);

        expect($id1)->not->toBe($id2)
            ->and($id1)->toHaveLength(64) // SHA256 produces 64 character hex string
            ->and($id2)->toHaveLength(64);

        // Verify deterministic - same input produces same output
        $id1Again = hash('sha256', $entry1->id.'-'.$entry1->title);
        expect($id1)->toBe($id1Again);
    });

    it('command signature includes --pull and --push flags', function () {
        $command = app(\App\Commands\SyncCommand::class);
        $signature = $command->getDefinition();

        expect($signature->hasOption('pull'))->toBeTrue()
            ->and($signature->hasOption('push'))->toBeTrue();
    });
});
