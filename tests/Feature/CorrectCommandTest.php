<?php

declare(strict_types=1);

use App\Services\CorrectionService;
use App\Services\QdrantService;

describe('CorrectCommand', function (): void {
    beforeEach(function (): void {
        $this->qdrant = mock(QdrantService::class);
        $this->correction = mock(CorrectionService::class);
        app()->instance(QdrantService::class, $this->qdrant);
        app()->instance(CorrectionService::class, $this->correction);
    });

    it('fails when entry not found', function (): void {
        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with('nonexistent-id')
            ->andReturn(null);

        $this->artisan('correct', [
            'id' => 'nonexistent-id',
            '--new-value' => 'corrected content',
        ])
            ->expectsOutputToContain('Entry not found: nonexistent-id')
            ->assertFailed();
    });

    it('fails when new-value option is missing', function (): void {
        $this->artisan('correct', ['id' => 'some-id'])
            ->expectsOutputToContain('The --new-value option is required.')
            ->assertFailed();
    });

    it('corrects an entry with no conflicts', function (): void {
        $entry = [
            'id' => 'entry-1',
            'title' => 'Original Title',
            'content' => 'Original content',
            'category' => 'debugging',
            'status' => 'validated',
            'confidence' => 80,
            'tags' => ['php'],
        ];

        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with('entry-1')
            ->andReturn($entry);

        $this->correction->shouldReceive('correct')
            ->once()
            ->with('entry-1', 'corrected content')
            ->andReturn([
                'corrected_entry_id' => 'new-entry-uuid',
                'superseded_ids' => [],
                'conflicts_found' => 0,
                'log_entry_id' => 'log-uuid',
            ]);

        $this->artisan('correct', [
            'id' => 'entry-1',
            '--new-value' => 'corrected content',
        ])
            ->expectsOutputToContain('Correction applied successfully!')
            ->expectsOutputToContain('entry-1')
            ->expectsOutputToContain('Original Title')
            ->expectsOutputToContain('new-entry-uuid')
            ->expectsOutputToContain('user correction')
            ->expectsOutputToContain('0')
            ->assertSuccessful();
    });

    it('corrects an entry with conflicts and shows superseded entries', function (): void {
        $entry = [
            'id' => 'entry-1',
            'title' => 'PHP Version Info',
            'content' => 'PHP minimum version is 7.4',
            'category' => 'architecture',
            'status' => 'validated',
            'confidence' => 90,
            'tags' => ['php', 'version'],
        ];

        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with('entry-1')
            ->andReturn($entry);

        $this->correction->shouldReceive('correct')
            ->once()
            ->with('entry-1', 'PHP minimum version is 8.2')
            ->andReturn([
                'corrected_entry_id' => 'corrected-uuid',
                'superseded_ids' => ['conflict-1', 'conflict-2'],
                'conflicts_found' => 2,
                'log_entry_id' => 'log-uuid',
            ]);

        $this->artisan('correct', [
            'id' => 'entry-1',
            '--new-value' => 'PHP minimum version is 8.2',
        ])
            ->expectsOutputToContain('Correction applied successfully!')
            ->expectsOutputToContain('Conflicts Found: 2')
            ->expectsOutputToContain('Entries Superseded: 2')
            ->expectsOutputToContain('conflict-1, conflict-2')
            ->assertSuccessful();
    });

    it('shows the view command hint after correction', function (): void {
        $entry = [
            'id' => 'entry-1',
            'title' => 'Test Entry',
            'content' => 'Test content',
            'status' => 'draft',
            'confidence' => 50,
            'tags' => [],
        ];

        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with('entry-1')
            ->andReturn($entry);

        $this->correction->shouldReceive('correct')
            ->once()
            ->andReturn([
                'corrected_entry_id' => 'new-uuid',
                'superseded_ids' => [],
                'conflicts_found' => 0,
                'log_entry_id' => 'log-uuid',
            ]);

        $this->artisan('correct', [
            'id' => 'entry-1',
            '--new-value' => 'Updated content',
        ])
            ->expectsOutputToContain('View corrected entry: ./know show new-uuid')
            ->assertSuccessful();
    });
});
