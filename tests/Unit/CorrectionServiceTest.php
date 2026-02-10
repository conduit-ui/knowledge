<?php

declare(strict_types=1);

use App\Services\CorrectionService;
use App\Services\QdrantService;

describe('CorrectionService', function (): void {
    beforeEach(function (): void {
        $this->qdrant = mock(QdrantService::class);
        $this->service = new CorrectionService($this->qdrant);
    });

    describe('correct', function (): void {
        it('throws exception when entry not found', function (): void {
            $this->qdrant->shouldReceive('getById')
                ->once()
                ->with('missing-id')
                ->andReturn(null);

            $this->service->correct('missing-id', 'new value');
        })->throws(\RuntimeException::class, 'Entry not found: missing-id');

        it('corrects an entry with no conflicts', function (): void {
            $original = [
                'id' => 'entry-1',
                'title' => 'Test Entry',
                'content' => 'Old content',
                'category' => 'debugging',
                'module' => null,
                'priority' => 'medium',
                'status' => 'validated',
                'confidence' => 80,
                'tags' => ['php'],
                'evidence' => null,
            ];

            $this->qdrant->shouldReceive('getById')
                ->once()
                ->with('entry-1')
                ->andReturn($original);

            // Search returns no conflicts (only the original itself)
            $this->qdrant->shouldReceive('search')
                ->once()
                ->andReturn(collect([
                    [
                        'id' => 'entry-1',
                        'score' => 1.0,
                        'title' => 'Test Entry',
                        'content' => 'Old content',
                        'status' => 'validated',
                        'tags' => ['php'],
                    ],
                ]));

            // Supersede original
            $this->qdrant->shouldReceive('updateFields')
                ->once()
                ->with('entry-1', Mockery::on(fn (array $fields): bool => $fields['status'] === 'deprecated'
                    && $fields['confidence'] === 10
                    && in_array('superseded', $fields['tags'], true)))
                ->andReturn(true);

            // Create corrected entry
            $this->qdrant->shouldReceive('upsert')
                ->once()
                ->with(Mockery::on(fn (array $entry): bool => $entry['title'] === 'Test Entry'
                    && $entry['content'] === 'Corrected content'
                    && $entry['confidence'] === 90
                    && $entry['status'] === 'validated'
                    && $entry['evidence'] === 'user correction'
                    && in_array('corrected', $entry['tags'], true)), 'default', true)
                ->andReturn(true);

            // Create log entry
            $this->qdrant->shouldReceive('upsert')
                ->once()
                ->with(Mockery::on(fn (array $entry): bool => str_contains($entry['title'], 'Correction Log')
                    && str_contains($entry['content'], 'entry-1')
                    && str_contains($entry['content'], 'Corrected content')
                    && in_array('correction-log', $entry['tags'], true)
                    && $entry['evidence'] === 'user correction'), 'default', true)
                ->andReturn(true);

            $result = $this->service->correct('entry-1', 'Corrected content');

            expect($result)->toHaveKeys(['corrected_entry_id', 'superseded_ids', 'conflicts_found', 'log_entry_id']);
            expect($result['superseded_ids'])->toBeEmpty();
            expect($result['conflicts_found'])->toBe(0);
        });

        it('corrects an entry and supersedes conflicts', function (): void {
            $original = [
                'id' => 'entry-1',
                'title' => 'PHP Version',
                'content' => 'PHP 7.4 required',
                'category' => 'architecture',
                'module' => null,
                'priority' => 'high',
                'status' => 'validated',
                'confidence' => 85,
                'tags' => ['php'],
                'evidence' => null,
            ];

            $this->qdrant->shouldReceive('getById')
                ->once()
                ->with('entry-1')
                ->andReturn($original);

            // Search returns conflicts
            $this->qdrant->shouldReceive('search')
                ->once()
                ->andReturn(collect([
                    [
                        'id' => 'entry-1',
                        'score' => 1.0,
                        'title' => 'PHP Version',
                        'content' => 'PHP 7.4 required',
                        'status' => 'validated',
                        'tags' => ['php'],
                    ],
                    [
                        'id' => 'conflict-1',
                        'score' => 0.92,
                        'title' => 'PHP Requirements',
                        'content' => 'Use PHP 7.4',
                        'status' => 'validated',
                        'tags' => ['php', 'requirements'],
                    ],
                    [
                        'id' => 'conflict-2',
                        'score' => 0.88,
                        'title' => 'Version Policy',
                        'content' => 'PHP 7.4 minimum',
                        'status' => 'draft',
                        'tags' => [],
                    ],
                ]));

            // Supersede conflict-1
            $this->qdrant->shouldReceive('updateFields')
                ->once()
                ->with('conflict-1', Mockery::on(fn (array $fields): bool => $fields['status'] === 'deprecated'
                    && $fields['confidence'] === 10
                    && in_array('superseded', $fields['tags'], true)))
                ->andReturn(true);

            // Supersede conflict-2
            $this->qdrant->shouldReceive('updateFields')
                ->once()
                ->with('conflict-2', Mockery::on(fn (array $fields): bool => $fields['status'] === 'deprecated'
                    && $fields['confidence'] === 10
                    && in_array('superseded', $fields['tags'], true)))
                ->andReturn(true);

            // Supersede original
            $this->qdrant->shouldReceive('updateFields')
                ->once()
                ->with('entry-1', Mockery::on(fn (array $fields): bool => $fields['status'] === 'deprecated'
                    && $fields['confidence'] === 10))
                ->andReturn(true);

            // Create corrected entry
            $this->qdrant->shouldReceive('upsert')
                ->once()
                ->with(Mockery::on(fn (array $entry): bool => $entry['content'] === 'PHP 8.2 required'
                    && $entry['evidence'] === 'user correction'
                    && $entry['status'] === 'validated'), 'default', true)
                ->andReturn(true);

            // Create log entry
            $this->qdrant->shouldReceive('upsert')
                ->once()
                ->with(Mockery::on(fn (array $entry): bool => str_contains($entry['content'], 'conflict-1')
                    && str_contains($entry['content'], 'conflict-2')), 'default', true)
                ->andReturn(true);

            $result = $this->service->correct('entry-1', 'PHP 8.2 required');

            expect($result['superseded_ids'])->toBe(['conflict-1', 'conflict-2']);
            expect($result['conflicts_found'])->toBe(2);
        });

        it('skips already deprecated entries when finding conflicts', function (): void {
            $original = [
                'id' => 'entry-1',
                'title' => 'Test',
                'content' => 'Test content',
                'category' => null,
                'module' => null,
                'priority' => 'medium',
                'status' => 'validated',
                'confidence' => 80,
                'tags' => [],
                'evidence' => null,
            ];

            $this->qdrant->shouldReceive('getById')
                ->once()
                ->with('entry-1')
                ->andReturn($original);

            // Search returns a deprecated entry that should be skipped
            $this->qdrant->shouldReceive('search')
                ->once()
                ->andReturn(collect([
                    [
                        'id' => 'entry-1',
                        'score' => 1.0,
                        'title' => 'Test',
                        'content' => 'Test content',
                        'status' => 'validated',
                        'tags' => [],
                    ],
                    [
                        'id' => 'deprecated-entry',
                        'score' => 0.90,
                        'title' => 'Old Test',
                        'content' => 'Old content',
                        'status' => 'deprecated',
                        'tags' => ['superseded'],
                    ],
                ]));

            // Only supersede original (no conflicts found)
            $this->qdrant->shouldReceive('updateFields')
                ->once()
                ->with('entry-1', Mockery::type('array'))
                ->andReturn(true);

            // Create corrected entry
            $this->qdrant->shouldReceive('upsert')
                ->once()
                ->with(Mockery::on(fn (array $entry): bool => $entry['evidence'] === 'user correction'), 'default', true)
                ->andReturn(true);

            // Create log entry
            $this->qdrant->shouldReceive('upsert')
                ->once()
                ->with(Mockery::on(fn (array $entry): bool => in_array('correction-log', $entry['tags'], true)), 'default', true)
                ->andReturn(true);

            $result = $this->service->correct('entry-1', 'Updated content');

            expect($result['conflicts_found'])->toBe(0);
            expect($result['superseded_ids'])->toBeEmpty();
        });

        it('skips entries below similarity threshold', function (): void {
            $original = [
                'id' => 'entry-1',
                'title' => 'Specific Topic',
                'content' => 'Very specific content',
                'category' => null,
                'module' => null,
                'priority' => 'medium',
                'status' => 'validated',
                'confidence' => 70,
                'tags' => [],
                'evidence' => null,
            ];

            $this->qdrant->shouldReceive('getById')
                ->once()
                ->with('entry-1')
                ->andReturn($original);

            // Search returns a low-similarity entry
            $this->qdrant->shouldReceive('search')
                ->once()
                ->andReturn(collect([
                    [
                        'id' => 'entry-1',
                        'score' => 1.0,
                        'title' => 'Specific Topic',
                        'content' => 'Very specific content',
                        'status' => 'validated',
                        'tags' => [],
                    ],
                    [
                        'id' => 'low-sim-entry',
                        'score' => 0.75,
                        'title' => 'Somewhat Related',
                        'content' => 'Different content',
                        'status' => 'validated',
                        'tags' => [],
                    ],
                ]));

            // Only supersede original
            $this->qdrant->shouldReceive('updateFields')
                ->once()
                ->with('entry-1', Mockery::type('array'))
                ->andReturn(true);

            // Create corrected entry
            $this->qdrant->shouldReceive('upsert')
                ->once()
                ->with(Mockery::on(fn (array $entry): bool => $entry['evidence'] === 'user correction'), 'default', true)
                ->andReturn(true);

            // Create log entry
            $this->qdrant->shouldReceive('upsert')
                ->once()
                ->with(Mockery::on(fn (array $entry): bool => in_array('correction-log', $entry['tags'], true)), 'default', true)
                ->andReturn(true);

            $result = $this->service->correct('entry-1', 'Updated content');

            expect($result['conflicts_found'])->toBe(0);
            expect($result['superseded_ids'])->toBeEmpty();
        });
    });

    describe('findConflicts', function (): void {
        it('returns empty array when no conflicts found', function (): void {
            $original = [
                'title' => 'Test',
                'content' => 'Content',
            ];

            $this->qdrant->shouldReceive('search')
                ->once()
                ->andReturn(collect([]));

            $conflicts = $this->service->findConflicts($original, 'entry-1');

            expect($conflicts)->toBeEmpty();
        });

        it('excludes the original entry from conflicts', function (): void {
            $original = [
                'title' => 'Test',
                'content' => 'Content',
            ];

            $this->qdrant->shouldReceive('search')
                ->once()
                ->andReturn(collect([
                    [
                        'id' => 'entry-1',
                        'score' => 1.0,
                        'title' => 'Test',
                        'content' => 'Content',
                        'status' => 'validated',
                        'tags' => [],
                    ],
                ]));

            $conflicts = $this->service->findConflicts($original, 'entry-1');

            expect($conflicts)->toBeEmpty();
        });
    });

    describe('supersedConflicts', function (): void {
        it('marks each conflict as superseded', function (): void {
            $conflicts = [
                [
                    'id' => 'c1',
                    'tags' => ['existing-tag'],
                ],
                [
                    'id' => 'c2',
                    'tags' => [],
                ],
            ];

            $this->qdrant->shouldReceive('updateFields')
                ->once()
                ->with('c1', Mockery::on(fn (array $fields): bool => $fields['status'] === 'deprecated'
                    && $fields['confidence'] === 10
                    && $fields['tags'] === ['existing-tag', 'superseded']))
                ->andReturn(true);

            $this->qdrant->shouldReceive('updateFields')
                ->once()
                ->with('c2', Mockery::on(fn (array $fields): bool => $fields['status'] === 'deprecated'
                    && $fields['confidence'] === 10
                    && $fields['tags'] === ['superseded']))
                ->andReturn(true);

            $ids = $this->service->supersedConflicts($conflicts, 'entry-1');

            expect($ids)->toBe(['c1', 'c2']);
        });

        it('returns empty array when no conflicts', function (): void {
            $ids = $this->service->supersedConflicts([], 'entry-1');

            expect($ids)->toBeEmpty();
        });

        it('does not duplicate superseded tag', function (): void {
            $conflicts = [
                [
                    'id' => 'c1',
                    'tags' => ['superseded', 'other'],
                ],
            ];

            $this->qdrant->shouldReceive('updateFields')
                ->once()
                ->with('c1', Mockery::on(fn (array $fields): bool => $fields['tags'] === ['superseded', 'other']))
                ->andReturn(true);

            $ids = $this->service->supersedConflicts($conflicts, 'entry-1');

            expect($ids)->toBe(['c1']);
        });
    });
});
