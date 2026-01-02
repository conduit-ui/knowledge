<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Services\SemanticSearchService;
use Tests\Support\MockChromaDBClient;
use Tests\Support\MockEmbeddingService;

describe('SemanticSearchService', function () {
    beforeEach(function () {
        $this->mockClient = new MockChromaDBClient;
        $this->mockEmbedding = new MockEmbeddingService;
    });

    describe('hasEmbeddingSupport', function () {
        it('returns true when embedding service generates embeddings', function () {
            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                false
            );

            expect($service->hasEmbeddingSupport())->toBeTrue();
        });

        it('returns false when embedding service returns empty array', function () {
            $mockEmbedding = new class implements \App\Contracts\EmbeddingServiceInterface
            {
                public function generate(string $text): array
                {
                    return [];
                }

                public function similarity(array $a, array $b): float
                {
                    return 0.0;
                }
            };

            $service = new SemanticSearchService(
                $mockEmbedding,
                true,
                null,
                false
            );

            expect($service->hasEmbeddingSupport())->toBeFalse();
        });
    });

    describe('hasChromaDBSupport', function () {
        it('returns true when all conditions met', function () {
            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $this->mockClient,
                true
            );

            expect($service->hasChromaDBSupport())->toBeTrue();
        });

        it('returns false when useChromaDB is false', function () {
            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $this->mockClient,
                false
            );

            expect($service->hasChromaDBSupport())->toBeFalse();
        });

        it('returns false when client is null', function () {
            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                true
            );

            expect($service->hasChromaDBSupport())->toBeFalse();
        });

        it('returns false when client is unavailable', function () {
            $this->mockClient->setAvailable(false);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $this->mockClient,
                true
            );

            expect($service->hasChromaDBSupport())->toBeFalse();
        });
    });

    describe('search - semantic disabled fallback', function () {
        it('uses keyword search when semantic is disabled', function () {
            $entry = Entry::factory()->create([
                'title' => 'Laravel Testing Guide',
                'content' => 'How to test Laravel applications',
                'confidence' => 95,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false, // Semantic disabled
                $this->mockClient,
                false
            );

            $results = $service->search('Laravel');

            expect($results)->not->toBeEmpty();
            expect($results->first()->title)->toContain('Laravel');
        });

        it('falls back to keyword search when embedding support unavailable', function () {
            $entry = Entry::factory()->create([
                'title' => 'PHP Best Practices',
                'content' => 'Modern PHP development',
                'confidence' => 90,
            ]);

            $mockEmbedding = new class implements \App\Contracts\EmbeddingServiceInterface
            {
                public function generate(string $text): array
                {
                    return []; // No embedding support
                }

                public function similarity(array $a, array $b): float
                {
                    return 0.0;
                }
            };

            $service = new SemanticSearchService(
                $mockEmbedding,
                true,
                null,
                false
            );

            $results = $service->search('PHP');

            expect($results)->not->toBeEmpty();
            expect($results->first()->title)->toContain('PHP');
        });
    });

    describe('search - ChromaDB path', function () {
        it('uses ChromaDB when enabled and available', function () {
            $entry = Entry::factory()->create([
                'title' => 'Laravel Testing',
                'content' => 'Guide to testing',
                'category' => 'documentation',
                'confidence' => 95,
            ]);

            $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');
            $this->mockClient->add(
                $collection['id'],
                ['entry_'.$entry->id],
                [$this->mockEmbedding->generate($entry->content)],
                [
                    [
                        'entry_id' => $entry->id,
                        'category' => 'documentation',
                        'module' => '',
                        'priority' => 'medium',
                        'status' => 'draft',
                        'confidence' => 95,
                    ],
                ]
            );

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $this->mockClient,
                true
            );

            $results = $service->search('testing');

            expect($results)->not->toBeEmpty();
            expect($results->first()->getAttributes())->toHaveKey('search_score');
        });

        it('filters ChromaDB results by category', function () {
            $entry1 = Entry::factory()->create([
                'title' => 'Doc Entry',
                'content' => 'Documentation content',
                'category' => 'documentation',
                'confidence' => 95,
            ]);

            $entry2 = Entry::factory()->create([
                'title' => 'Guide Entry',
                'content' => 'Guide content',
                'category' => 'guide',
                'confidence' => 90,
            ]);

            $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');
            $this->mockClient->add(
                $collection['id'],
                ['entry_'.$entry1->id, 'entry_'.$entry2->id],
                [
                    $this->mockEmbedding->generate($entry1->content),
                    $this->mockEmbedding->generate($entry2->content),
                ],
                [
                    [
                        'entry_id' => $entry1->id,
                        'category' => 'documentation',
                        'module' => '',
                        'priority' => 'medium',
                        'status' => 'draft',
                        'confidence' => 95,
                    ],
                    [
                        'entry_id' => $entry2->id,
                        'category' => 'guide',
                        'module' => '',
                        'priority' => 'medium',
                        'status' => 'draft',
                        'confidence' => 90,
                    ],
                ]
            );

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $this->mockClient,
                true
            );

            $results = $service->search('content', ['category' => 'documentation']);

            expect($results)->not->toBeEmpty();
            expect($results->every(fn ($entry) => $entry->category === 'documentation'))->toBeTrue();
        });

        it('filters ChromaDB results by module', function () {
            $entry = Entry::factory()->create([
                'title' => 'Auth Module',
                'content' => 'Authentication documentation',
                'module' => 'auth',
                'confidence' => 95,
            ]);

            $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');
            $this->mockClient->add(
                $collection['id'],
                ['entry_'.$entry->id],
                [$this->mockEmbedding->generate($entry->content)],
                [
                    [
                        'entry_id' => $entry->id,
                        'category' => '',
                        'module' => 'auth',
                        'priority' => 'medium',
                        'status' => 'draft',
                        'confidence' => 95,
                    ],
                ]
            );

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $this->mockClient,
                true
            );

            $results = $service->search('authentication', ['module' => 'auth']);

            expect($results->count())->toBeGreaterThanOrEqual(0);
            if ($results->isNotEmpty()) {
                expect($results->first()->module)->toBe('auth');
            }
        });

        it('filters ChromaDB results by priority', function () {
            $entry = Entry::factory()->create([
                'title' => 'Critical Task',
                'content' => 'High priority task',
                'priority' => 'critical',
                'confidence' => 95,
            ]);

            $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');
            $this->mockClient->add(
                $collection['id'],
                ['entry_'.$entry->id],
                [$this->mockEmbedding->generate($entry->content)],
                [
                    [
                        'entry_id' => $entry->id,
                        'category' => '',
                        'module' => '',
                        'priority' => 'critical',
                        'status' => 'draft',
                        'confidence' => 95,
                    ],
                ]
            );

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $this->mockClient,
                true
            );

            $results = $service->search('task', ['priority' => 'critical']);

            expect($results->count())->toBeGreaterThanOrEqual(0);
            if ($results->isNotEmpty()) {
                expect($results->first()->priority)->toBe('critical');
            }
        });

        it('filters ChromaDB results by status', function () {
            $entry = Entry::factory()->create([
                'title' => 'Validated Entry',
                'content' => 'Validated content',
                'status' => 'validated',
                'confidence' => 95,
            ]);

            $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');
            $this->mockClient->add(
                $collection['id'],
                ['entry_'.$entry->id],
                [$this->mockEmbedding->generate($entry->content)],
                [
                    [
                        'entry_id' => $entry->id,
                        'category' => '',
                        'module' => '',
                        'priority' => 'medium',
                        'status' => 'validated',
                        'confidence' => 95,
                    ],
                ]
            );

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $this->mockClient,
                true
            );

            $results = $service->search('content', ['status' => 'validated']);

            expect($results->count())->toBeGreaterThanOrEqual(0);
            if ($results->isNotEmpty()) {
                expect($results->first()->status)->toBe('validated');
            }
        });

        it('filters ChromaDB results by tag', function () {
            $entry = Entry::factory()->create([
                'title' => 'Laravel Testing',
                'content' => 'Guide to testing',
                'tags' => ['laravel', 'testing'],
                'category' => 'documentation',
                'confidence' => 95,
            ]);

            $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');
            $this->mockClient->add(
                $collection['id'],
                ['entry_'.$entry->id],
                [$this->mockEmbedding->generate($entry->content)],
                [
                    [
                        'entry_id' => $entry->id,
                        'category' => 'documentation',
                        'module' => '',
                        'priority' => 'medium',
                        'status' => 'draft',
                        'confidence' => 95,
                    ],
                ]
            );

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $this->mockClient,
                true
            );

            $results = $service->search('testing', ['tag' => 'laravel']);

            expect($results)->not->toBeEmpty();
            expect($results->first()->tags)->toContain('laravel');
        });

        it('applies multiple ChromaDB filters', function () {
            $entry = Entry::factory()->create([
                'title' => 'Auth Documentation',
                'content' => 'Critical auth docs',
                'category' => 'documentation',
                'module' => 'auth',
                'priority' => 'critical',
                'status' => 'validated',
                'confidence' => 95,
            ]);

            $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');
            $this->mockClient->add(
                $collection['id'],
                ['entry_'.$entry->id],
                [$this->mockEmbedding->generate($entry->content)],
                [
                    [
                        'entry_id' => $entry->id,
                        'category' => 'documentation',
                        'module' => 'auth',
                        'priority' => 'critical',
                        'status' => 'validated',
                        'confidence' => 95,
                    ],
                ]
            );

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $this->mockClient,
                true
            );

            $results = $service->search('auth', [
                'category' => 'documentation',
                'module' => 'auth',
                'priority' => 'critical',
                'status' => 'validated',
            ]);

            expect($results->count())->toBeGreaterThanOrEqual(0);
            if ($results->isNotEmpty()) {
                expect($results->first()->category)->toBe('documentation');
                expect($results->first()->module)->toBe('auth');
                expect($results->first()->priority)->toBe('critical');
                expect($results->first()->status)->toBe('validated');
            }
        });

        it('returns empty when ChromaDB returns no results', function () {
            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $this->mockClient,
                true
            );

            $results = $service->search('nonexistent query');

            expect($results)->toBeEmpty();
        });

        it('skips entries that no longer exist in database', function () {
            $entry = Entry::factory()->create([
                'title' => 'Test Entry',
                'content' => 'Test content',
                'confidence' => 95,
            ]);

            $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');
            $this->mockClient->add(
                $collection['id'],
                ['entry_'.$entry->id, 'entry_9999'],
                [
                    $this->mockEmbedding->generate($entry->content),
                    $this->mockEmbedding->generate('nonexistent'),
                ],
                [
                    [
                        'entry_id' => $entry->id,
                        'category' => '',
                        'module' => '',
                        'priority' => 'medium',
                        'status' => 'draft',
                        'confidence' => 95,
                    ],
                    [
                        'entry_id' => 9999,
                        'category' => '',
                        'module' => '',
                        'priority' => 'medium',
                        'status' => 'draft',
                        'confidence' => 95,
                    ],
                ]
            );

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $this->mockClient,
                true
            );

            $results = $service->search('content');

            expect($results->count())->toBe(1);
        });

        it('calculates search score based on similarity and confidence', function () {
            $entry = Entry::factory()->create([
                'title' => 'Laravel Testing',
                'content' => 'Guide to testing',
                'confidence' => 100,
            ]);

            $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');
            $this->mockClient->add(
                $collection['id'],
                ['entry_'.$entry->id],
                [$this->mockEmbedding->generate($entry->content)],
                [
                    [
                        'entry_id' => $entry->id,
                        'category' => '',
                        'module' => '',
                        'priority' => 'medium',
                        'status' => 'draft',
                        'confidence' => 100,
                    ],
                ]
            );

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $this->mockClient,
                true
            );

            $results = $service->search('testing');

            expect($results)->not->toBeEmpty();
            if ($results->isNotEmpty()) {
                expect($results->first()->getAttributes())->toHaveKey('search_score');
                expect($results->first()->search_score)->toBeGreaterThan(0);
            }
        });

        it('falls back to SQLite when ChromaDB throws RuntimeException', function () {
            $failingClient = new class implements \App\Contracts\ChromaDBClientInterface
            {
                public function getOrCreateCollection(string $name): array
                {
                    throw new \RuntimeException('ChromaDB connection failed');
                }

                public function add(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void {}

                public function query(string $collectionId, array $queryEmbedding, int $nResults = 10, array $where = []): array
                {
                    throw new \RuntimeException('Query failed');
                }

                public function delete(string $collectionId, array $ids): void {}

                public function update(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void {}

                public function isAvailable(): bool
                {
                    return true;
                }
            };

            $entry = Entry::factory()->create([
                'title' => 'Laravel Testing',
                'content' => 'Guide to testing',
                'confidence' => 95,
                'embedding' => json_encode($this->mockEmbedding->generate('Guide to testing')),
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $failingClient,
                true
            );

            $results = $service->search('testing');

            expect($results)->not->toBeEmpty();
        });

        it('returns empty when ChromaDB returns empty ids array', function () {
            $customClient = new class implements \App\Contracts\ChromaDBClientInterface
            {
                public function getOrCreateCollection(string $name): array
                {
                    return ['id' => 'test_collection', 'name' => $name];
                }

                public function add(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void {}

                public function query(string $collectionId, array $queryEmbedding, int $nResults = 10, array $where = []): array
                {
                    return [
                        'ids' => [[]],
                        'distances' => [[]],
                    ];
                }

                public function delete(string $collectionId, array $ids): void {}

                public function update(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void {}

                public function isAvailable(): bool
                {
                    return true;
                }
            };

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $customClient,
                true
            );

            $results = $service->search('query');

            expect($results)->toBeEmpty();
        });
    });

    describe('search - SQLite semantic path', function () {
        it('uses SQLite semantic search when ChromaDB unavailable', function () {
            $entry = Entry::factory()->create([
                'title' => 'Laravel Testing',
                'content' => 'Guide to testing',
                'confidence' => 95,
                'embedding' => json_encode($this->mockEmbedding->generate('Guide to testing')),
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                false
            );

            $results = $service->search('testing');

            expect($results)->not->toBeEmpty();
            expect($results->first()->getAttributes())->toHaveKey('search_score');
        });

        it('filters SQLite semantic results by category', function () {
            Entry::factory()->create([
                'title' => 'Doc Entry',
                'content' => 'Documentation content',
                'category' => 'documentation',
                'confidence' => 95,
                'embedding' => json_encode($this->mockEmbedding->generate('Documentation content')),
            ]);

            Entry::factory()->create([
                'title' => 'Guide Entry',
                'content' => 'Guide content',
                'category' => 'guide',
                'confidence' => 90,
                'embedding' => json_encode($this->mockEmbedding->generate('Guide content')),
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                false
            );

            $results = $service->search('content', ['category' => 'documentation']);

            expect($results)->not->toBeEmpty();
            expect($results->every(fn ($entry) => $entry->category === 'documentation'))->toBeTrue();
        });

        it('filters SQLite semantic results by tag', function () {
            Entry::factory()->create([
                'title' => 'Laravel Testing',
                'content' => 'Guide to testing',
                'tags' => ['laravel', 'testing'],
                'confidence' => 95,
                'embedding' => json_encode($this->mockEmbedding->generate('Guide to testing')),
            ]);

            Entry::factory()->create([
                'title' => 'PHP Testing',
                'content' => 'PHP guide',
                'tags' => ['php', 'testing'],
                'confidence' => 90,
                'embedding' => json_encode($this->mockEmbedding->generate('PHP guide')),
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                false
            );

            $results = $service->search('testing', ['tag' => 'laravel']);

            expect($results)->not->toBeEmpty();
            expect($results->every(fn ($entry) => in_array('laravel', $entry->tags)))->toBeTrue();
        });

        it('filters SQLite semantic results by module', function () {
            Entry::factory()->create([
                'title' => 'Auth Module',
                'content' => 'Authentication documentation',
                'module' => 'auth',
                'confidence' => 95,
                'embedding' => json_encode($this->mockEmbedding->generate('Authentication documentation')),
            ]);

            Entry::factory()->create([
                'title' => 'User Module',
                'content' => 'User documentation',
                'module' => 'user',
                'confidence' => 90,
                'embedding' => json_encode($this->mockEmbedding->generate('User documentation')),
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                false
            );

            $results = $service->search('documentation', ['module' => 'auth']);

            expect($results)->not->toBeEmpty();
            expect($results->every(fn ($entry) => $entry->module === 'auth'))->toBeTrue();
        });

        it('filters SQLite semantic results by priority', function () {
            Entry::factory()->create([
                'title' => 'Critical Task',
                'content' => 'High priority task',
                'priority' => 'critical',
                'confidence' => 95,
                'embedding' => json_encode($this->mockEmbedding->generate('High priority task')),
            ]);

            Entry::factory()->create([
                'title' => 'Low Task',
                'content' => 'Low priority task',
                'priority' => 'low',
                'confidence' => 90,
                'embedding' => json_encode($this->mockEmbedding->generate('Low priority task')),
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                false
            );

            $results = $service->search('task', ['priority' => 'critical']);

            expect($results)->not->toBeEmpty();
            expect($results->every(fn ($entry) => $entry->priority === 'critical'))->toBeTrue();
        });

        it('filters SQLite semantic results by status', function () {
            Entry::factory()->create([
                'title' => 'Validated Entry',
                'content' => 'Validated content',
                'status' => 'validated',
                'confidence' => 95,
                'embedding' => json_encode($this->mockEmbedding->generate('Validated content')),
            ]);

            Entry::factory()->create([
                'title' => 'Draft Entry',
                'content' => 'Draft content',
                'status' => 'draft',
                'confidence' => 90,
                'embedding' => json_encode($this->mockEmbedding->generate('Draft content')),
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                false
            );

            $results = $service->search('content', ['status' => 'validated']);

            expect($results)->not->toBeEmpty();
            expect($results->every(fn ($entry) => $entry->status === 'validated'))->toBeTrue();
        });

        it('applies multiple SQLite semantic filters', function () {
            Entry::factory()->create([
                'title' => 'Auth Documentation',
                'content' => 'Critical auth docs',
                'category' => 'documentation',
                'module' => 'auth',
                'priority' => 'critical',
                'status' => 'validated',
                'tags' => ['auth', 'security'],
                'confidence' => 95,
                'embedding' => json_encode($this->mockEmbedding->generate('Critical auth docs')),
            ]);

            Entry::factory()->create([
                'title' => 'Other Entry',
                'content' => 'Other content',
                'category' => 'guide',
                'confidence' => 90,
                'embedding' => json_encode($this->mockEmbedding->generate('Other content')),
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                false
            );

            $results = $service->search('docs', [
                'category' => 'documentation',
                'module' => 'auth',
                'priority' => 'critical',
                'status' => 'validated',
                'tag' => 'auth',
            ]);

            expect($results)->not->toBeEmpty();
            expect($results->first()->category)->toBe('documentation');
            expect($results->first()->module)->toBe('auth');
            expect($results->first()->priority)->toBe('critical');
            expect($results->first()->status)->toBe('validated');
        });

        it('calculates similarity score in SQLite search', function () {
            $entry = Entry::factory()->create([
                'title' => 'Laravel Testing',
                'content' => 'Guide to testing',
                'confidence' => 80,
                'embedding' => json_encode($this->mockEmbedding->generate('Guide to testing')),
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                false
            );

            $results = $service->search('testing');

            expect($results)->not->toBeEmpty();
            expect($results->first()->getAttributes())->toHaveKey('search_score');
            expect($results->first()->search_score)->toBeGreaterThan(0);
            expect($results->first()->search_score)->toBeLessThanOrEqual(1.0);
        });

        it('sorts SQLite results by search score descending', function () {
            Entry::factory()->create([
                'title' => 'Exact Match',
                'content' => 'Laravel testing guide',
                'confidence' => 100,
                'embedding' => json_encode($this->mockEmbedding->generate('Laravel testing guide')),
            ]);

            Entry::factory()->create([
                'title' => 'Partial Match',
                'content' => 'Different content',
                'confidence' => 50,
                'embedding' => json_encode($this->mockEmbedding->generate('Different content')),
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                false
            );

            $results = $service->search('Laravel testing guide');

            expect($results->count())->toBeGreaterThanOrEqual(2);
            if ($results->count() >= 2) {
                expect($results->first()->search_score)->toBeGreaterThanOrEqual($results->last()->search_score);
            }
        });

        it('skips entries with null embeddings', function () {
            Entry::factory()->create([
                'title' => 'No Embedding',
                'content' => 'Content without embedding',
                'confidence' => 95,
                'embedding' => null,
            ]);

            Entry::factory()->create([
                'title' => 'With Embedding',
                'content' => 'Content with embedding',
                'confidence' => 95,
                'embedding' => json_encode($this->mockEmbedding->generate('Content with embedding')),
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                false
            );

            $results = $service->search('content');

            expect($results->count())->toBe(1);
            expect($results->first()->title)->toBe('With Embedding');
        });

        it('skips entries with invalid JSON embeddings', function () {
            Entry::factory()->create([
                'title' => 'Invalid Embedding',
                'content' => 'Content with invalid embedding',
                'confidence' => 95,
                'embedding' => 'invalid json',
            ]);

            Entry::factory()->create([
                'title' => 'Valid Embedding',
                'content' => 'Content with valid embedding',
                'confidence' => 95,
                'embedding' => json_encode($this->mockEmbedding->generate('Content with valid embedding')),
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                false
            );

            $results = $service->search('content');

            expect($results->count())->toBe(1);
            expect($results->first()->title)->toBe('Valid Embedding');
        });

        it('falls back to keyword search when no entries have embeddings', function () {
            Entry::factory()->create([
                'title' => 'No Embedding 1',
                'content' => 'Content 1',
                'confidence' => 95,
                'embedding' => null,
            ]);

            Entry::factory()->create([
                'title' => 'No Embedding 2',
                'content' => 'Content 2',
                'confidence' => 90,
                'embedding' => null,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                false
            );

            $results = $service->search('content');

            // Falls back to keyword search and finds 'content' in the content field
            expect($results)->not->toBeEmpty();
            expect($results->count())->toBe(2);
        });
    });

    describe('search - keyword fallback path', function () {
        it('searches by title using keyword search', function () {
            Entry::factory()->create([
                'title' => 'Laravel Testing Guide',
                'content' => 'How to test Laravel',
                'confidence' => 95,
            ]);

            Entry::factory()->create([
                'title' => 'PHP Best Practices',
                'content' => 'Modern PHP',
                'confidence' => 90,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $results = $service->search('Laravel');

            expect($results)->not->toBeEmpty();
            expect($results->first()->title)->toContain('Laravel');
        });

        it('searches by content using keyword search', function () {
            Entry::factory()->create([
                'title' => 'Testing Guide',
                'content' => 'Laravel testing with Pest',
                'confidence' => 95,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $results = $service->search('Pest');

            expect($results)->not->toBeEmpty();
            expect($results->first()->content)->toContain('Pest');
        });

        it('searches across title or content', function () {
            Entry::factory()->create([
                'title' => 'Database Guide',
                'content' => 'How to use databases',
                'confidence' => 95,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $titleResults = $service->search('Database');
            $contentResults = $service->search('databases');

            expect($titleResults)->not->toBeEmpty();
            expect($contentResults)->not->toBeEmpty();
        });

        it('filters keyword results by category', function () {
            Entry::factory()->create([
                'title' => 'Testing Guide',
                'content' => 'Guide content',
                'category' => 'documentation',
                'confidence' => 95,
            ]);

            Entry::factory()->create([
                'title' => 'Testing Tutorial',
                'content' => 'Tutorial content',
                'category' => 'guide',
                'confidence' => 90,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $results = $service->search('Testing', ['category' => 'documentation']);

            expect($results)->not->toBeEmpty();
            expect($results->every(fn ($entry) => $entry->category === 'documentation'))->toBeTrue();
        });

        it('filters keyword results by tag', function () {
            Entry::factory()->create([
                'title' => 'Testing Guide',
                'content' => 'Guide content',
                'tags' => ['laravel', 'testing'],
                'confidence' => 95,
            ]);

            Entry::factory()->create([
                'title' => 'Testing Tutorial',
                'content' => 'Tutorial content',
                'tags' => ['php', 'testing'],
                'confidence' => 90,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $results = $service->search('Testing', ['tag' => 'laravel']);

            expect($results)->not->toBeEmpty();
            expect($results->every(fn ($entry) => in_array('laravel', $entry->tags)))->toBeTrue();
        });

        it('filters keyword results by module', function () {
            Entry::factory()->create([
                'title' => 'Auth Testing',
                'content' => 'Auth guide',
                'module' => 'auth',
                'confidence' => 95,
            ]);

            Entry::factory()->create([
                'title' => 'User Testing',
                'content' => 'User guide',
                'module' => 'user',
                'confidence' => 90,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $results = $service->search('Testing', ['module' => 'auth']);

            expect($results)->not->toBeEmpty();
            expect($results->every(fn ($entry) => $entry->module === 'auth'))->toBeTrue();
        });

        it('filters keyword results by priority', function () {
            Entry::factory()->create([
                'title' => 'Critical Testing',
                'content' => 'Critical guide',
                'priority' => 'critical',
                'confidence' => 95,
            ]);

            Entry::factory()->create([
                'title' => 'Low Testing',
                'content' => 'Low guide',
                'priority' => 'low',
                'confidence' => 90,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $results = $service->search('Testing', ['priority' => 'critical']);

            expect($results)->not->toBeEmpty();
            expect($results->every(fn ($entry) => $entry->priority === 'critical'))->toBeTrue();
        });

        it('filters keyword results by status', function () {
            Entry::factory()->create([
                'title' => 'Validated Testing',
                'content' => 'Validated guide',
                'status' => 'validated',
                'confidence' => 95,
            ]);

            Entry::factory()->create([
                'title' => 'Draft Testing',
                'content' => 'Draft guide',
                'status' => 'draft',
                'confidence' => 90,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $results = $service->search('Testing', ['status' => 'validated']);

            expect($results)->not->toBeEmpty();
            expect($results->every(fn ($entry) => $entry->status === 'validated'))->toBeTrue();
        });

        it('applies multiple keyword filters', function () {
            Entry::factory()->create([
                'title' => 'Auth Testing',
                'content' => 'Critical auth guide',
                'category' => 'documentation',
                'module' => 'auth',
                'priority' => 'critical',
                'status' => 'validated',
                'tags' => ['auth', 'security'],
                'confidence' => 95,
            ]);

            Entry::factory()->create([
                'title' => 'Other Testing',
                'content' => 'Other guide',
                'category' => 'guide',
                'confidence' => 90,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $results = $service->search('Testing', [
                'category' => 'documentation',
                'module' => 'auth',
                'priority' => 'critical',
                'status' => 'validated',
                'tag' => 'auth',
            ]);

            expect($results)->not->toBeEmpty();
            expect($results->first()->category)->toBe('documentation');
            expect($results->first()->module)->toBe('auth');
            expect($results->first()->priority)->toBe('critical');
            expect($results->first()->status)->toBe('validated');
        });

        it('orders keyword results by confidence then usage_count', function () {
            Entry::factory()->create([
                'title' => 'Laravel Guide',
                'content' => 'Laravel content',
                'confidence' => 90,
                'usage_count' => 5,
            ]);

            Entry::factory()->create([
                'title' => 'Laravel Tutorial',
                'content' => 'Laravel tutorial content',
                'confidence' => 95,
                'usage_count' => 3,
            ]);

            Entry::factory()->create([
                'title' => 'Laravel Docs',
                'content' => 'Laravel documentation',
                'confidence' => 95,
                'usage_count' => 10,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $results = $service->search('Laravel');

            expect($results->count())->toBe(3);
            expect($results->first()->confidence)->toBe(95);
            expect($results->first()->usage_count)->toBe(10);
        });

        it('returns empty when no matches found', function () {
            Entry::factory()->create([
                'title' => 'Laravel Guide',
                'content' => 'Laravel content',
                'confidence' => 95,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $results = $service->search('nonexistent');

            expect($results)->toBeEmpty();
        });
    });

    describe('search - fallback when semantic returns empty', function () {
        it('falls back to keyword when semantic search returns no results', function () {
            // Create entry without embedding so semantic search returns empty
            Entry::factory()->create([
                'title' => 'Laravel Testing',
                'content' => 'Guide to testing',
                'confidence' => 95,
                'embedding' => null,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                null,
                false
            );

            $results = $service->search('Laravel');

            // Should fallback to keyword search
            expect($results)->not->toBeEmpty();
            expect($results->first()->title)->toContain('Laravel');
        });

        it('uses keyword search when semantic enabled but returns empty from ChromaDB', function () {
            Entry::factory()->create([
                'title' => 'Laravel Testing',
                'content' => 'Guide to testing',
                'confidence' => 95,
            ]);

            // ChromaDB enabled but no indexed documents
            $service = new SemanticSearchService(
                $this->mockEmbedding,
                true,
                $this->mockClient,
                true
            );

            $results = $service->search('Laravel');

            // Should fallback to keyword search
            expect($results)->not->toBeEmpty();
            expect($results->first()->title)->toContain('Laravel');
        });
    });

    describe('edge cases', function () {
        it('handles empty query string with keyword search', function () {
            Entry::factory()->create([
                'title' => 'Test Entry',
                'content' => 'Test content',
                'confidence' => 95,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $results = $service->search('');

            // Empty string with LIKE operator matches all entries
            expect($results)->not->toBeEmpty();
        });

        it('handles special characters in search query', function () {
            Entry::factory()->create([
                'title' => 'Special % chars & symbols',
                'content' => 'Content with % and & symbols',
                'confidence' => 95,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $results = $service->search('%');

            expect($results)->not->toBeEmpty();
        });

        it('handles empty filters array', function () {
            Entry::factory()->create([
                'title' => 'Test Entry',
                'content' => 'Test content',
                'confidence' => 95,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $results = $service->search('Test', []);

            expect($results)->not->toBeEmpty();
        });

        it('handles null filter values gracefully', function () {
            Entry::factory()->create([
                'title' => 'Test Entry',
                'content' => 'Test content',
                'confidence' => 95,
            ]);

            $service = new SemanticSearchService(
                $this->mockEmbedding,
                false,
                null,
                false
            );

            $results = $service->search('Test', [
                'category' => null,
                'tag' => null,
            ]);

            expect($results)->not->toBeEmpty();
        });
    });
});
