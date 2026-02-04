<?php

declare(strict_types=1);

use App\Services\QdrantService;

describe('KnowledgeArchiveCommand', function (): void {
    beforeEach(function (): void {
        $this->qdrant = mock(QdrantService::class);
        app()->instance(QdrantService::class, $this->qdrant);
    });

    it('validates entry ID is numeric', function (): void {
        $this->artisan('archive', ['id' => 'not-numeric'])
            ->expectsOutput('Entry ID must be a number.')
            ->assertFailed();
    });

    it('fails when entry not found', function (): void {
        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->artisan('archive', ['id' => '999'])
            ->expectsOutput('Entry not found with ID: 999')
            ->assertFailed();
    });

    it('archives an active entry', function (): void {
        $entry = [
            'id' => 1,
            'title' => 'Test Entry',
            'content' => 'Test content',
            'status' => 'validated',
            'confidence' => 95,
        ];

        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with(1)
            ->andReturn($entry);

        $this->qdrant->shouldReceive('updateFields')
            ->once()
            ->with(1, [
                'status' => 'deprecated',
                'confidence' => 0,
            ]);

        $this->artisan('archive', ['id' => '1'])
            ->expectsOutputToContain('Entry #1 has been archived.')
            ->expectsOutputToContain('Title: Test Entry')
            ->expectsOutputToContain('Status: validated -> deprecated')
            ->expectsOutputToContain('Restore with: knowledge:archive 1 --restore')
            ->assertSuccessful();
    });

    it('warns when archiving already archived entry', function (): void {
        $entry = [
            'id' => 1,
            'title' => 'Archived Entry',
            'content' => 'Test content',
            'status' => 'deprecated',
            'confidence' => 0,
        ];

        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with(1)
            ->andReturn($entry);

        $this->artisan('archive', ['id' => '1'])
            ->expectsOutputToContain('Entry #1 is already archived.')
            ->assertSuccessful();
    });

    it('restores archived entry', function (): void {
        $entry = [
            'id' => 1,
            'title' => 'Archived Entry',
            'content' => 'Test content',
            'status' => 'deprecated',
            'confidence' => 0,
        ];

        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with(1)
            ->andReturn($entry);

        $this->qdrant->shouldReceive('updateFields')
            ->once()
            ->with(1, [
                'status' => 'draft',
                'confidence' => 50,
            ]);

        $this->artisan('archive', ['id' => '1', '--restore' => true])
            ->expectsOutputToContain('Entry #1 has been restored.')
            ->expectsOutputToContain('Title: Archived Entry')
            ->expectsOutputToContain('Status: deprecated -> draft')
            ->expectsOutputToContain('Confidence: 50%')
            ->expectsOutputToContain('Validate with: knowledge:validate 1')
            ->assertSuccessful();
    });

    it('warns when restoring non-archived entry', function (): void {
        $entry = [
            'id' => 1,
            'title' => 'Active Entry',
            'content' => 'Test content',
            'status' => 'validated',
            'confidence' => 95,
        ];

        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with(1)
            ->andReturn($entry);

        $this->artisan('archive', ['id' => '1', '--restore' => true])
            ->expectsOutputToContain('Entry #1 is not archived (status: validated).')
            ->assertSuccessful();
    });

    it('archives draft entry', function (): void {
        $entry = [
            'id' => 2,
            'title' => 'Draft Entry',
            'content' => 'Draft content',
            'status' => 'draft',
            'confidence' => 50,
        ];

        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with(2)
            ->andReturn($entry);

        $this->qdrant->shouldReceive('updateFields')
            ->once()
            ->with(2, [
                'status' => 'deprecated',
                'confidence' => 0,
            ]);

        $this->artisan('archive', ['id' => '2'])
            ->expectsOutputToContain('Entry #2 has been archived.')
            ->expectsOutputToContain('Status: draft -> deprecated')
            ->assertSuccessful();
    });
});
