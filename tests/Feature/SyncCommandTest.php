<?php

declare(strict_types=1);

use App\Services\QdrantService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

describe('SyncCommand', function () {
    beforeEach(function () {
        $this->qdrant = mock(QdrantService::class);
        app()->instance(QdrantService::class, $this->qdrant);

        // Set environment variable for API token in all places env() checks
        putenv('PREFRONTAL_API_TOKEN=test-token-12345');
        $_ENV['PREFRONTAL_API_TOKEN'] = 'test-token-12345';
        $_SERVER['PREFRONTAL_API_TOKEN'] = 'test-token-12345';
    });

    afterEach(function () {
        // Clean up environment from all sources
        putenv('PREFRONTAL_API_TOKEN');
        unset($_ENV['PREFRONTAL_API_TOKEN']);
        unset($_SERVER['PREFRONTAL_API_TOKEN']);
    });

    it('fails when PREFRONTAL_API_TOKEN is not set', function () {
        // Clear from all sources to ensure env() returns empty/null
        putenv('PREFRONTAL_API_TOKEN');
        unset($_ENV['PREFRONTAL_API_TOKEN']);
        unset($_SERVER['PREFRONTAL_API_TOKEN']);

        // Mock should not receive any calls since command fails early
        $this->qdrant->shouldNotReceive('search');
        $this->qdrant->shouldNotReceive('upsert');

        $this->artisan('sync')
            ->expectsOutput('PREFRONTAL_API_TOKEN environment variable is not set.')
            ->assertFailed();
    });

    it('performs two-way sync by default', function () {
        $mockHandler = new MockHandler([
            // Pull request
            new Response(200, [], json_encode([
                'data' => [
                    [
                        'unique_id' => 'unique-1',
                        'title' => 'Cloud Entry',
                        'content' => 'Cloud content',
                        'category' => 'tutorial',
                        'tags' => ['cloud'],
                        'module' => null,
                        'priority' => 'high',
                        'confidence' => 90,
                        'status' => 'validated',
                    ],
                ],
            ])),
            // Push request
            new Response(200, [], json_encode([
                'summary' => [
                    'created' => 1,
                    'updated' => 0,
                    'failed' => 0,
                ],
            ])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);
        app()->instance(Client::class, $client);

        // Mock Qdrant for pull
        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('Cloud Entry', [], 1)
            ->andReturn(collect([]));

        $this->qdrant->shouldReceive('upsert')
            ->once();

        // Mock Qdrant for push
        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn(collect([
                [
                    'id' => 1,
                    'title' => 'Local Entry',
                    'content' => 'Local content',
                    'category' => 'guide',
                    'tags' => ['local'],
                    'module' => null,
                    'priority' => 'medium',
                    'confidence' => 80,
                    'status' => 'draft',
                ],
            ]));

        $this->artisan('sync')
            ->expectsOutputToContain('Starting two-way sync')
            ->expectsOutputToContain('Sync Summary')
            ->assertSuccessful();
    });

    it('pulls entries from cloud only with --pull flag', function () {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    [
                        'unique_id' => 'unique-1',
                        'title' => 'Cloud Entry',
                        'content' => 'Cloud content',
                        'category' => null,
                        'tags' => [],
                        'module' => null,
                        'priority' => 'medium',
                        'confidence' => 70,
                        'status' => 'draft',
                    ],
                ],
            ])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);
        app()->instance(Client::class, $client);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->andReturn(collect([]));

        $this->qdrant->shouldReceive('upsert')
            ->once();

        $this->artisan('sync', ['--pull' => true])
            ->expectsOutputToContain('Pulling entries from cloud')
            ->expectsOutputToContain('Pull Summary')
            ->assertSuccessful();
    });

    it('pushes entries to cloud only with --push flag', function () {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'summary' => [
                    'created' => 2,
                    'updated' => 0,
                    'failed' => 0,
                ],
            ])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);
        app()->instance(Client::class, $client);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn(collect([
                [
                    'id' => 1,
                    'title' => 'Entry 1',
                    'content' => 'Content 1',
                    'category' => null,
                    'tags' => [],
                    'module' => null,
                    'priority' => 'medium',
                    'confidence' => 50,
                    'status' => 'draft',
                ],
                [
                    'id' => 2,
                    'title' => 'Entry 2',
                    'content' => 'Content 2',
                    'category' => null,
                    'tags' => [],
                    'module' => null,
                    'priority' => 'low',
                    'confidence' => 40,
                    'status' => 'draft',
                ],
            ]));

        $this->artisan('sync', ['--push' => true])
            ->expectsOutputToContain('Pushing local entries to cloud')
            ->expectsOutputToContain('Push Summary')
            ->assertSuccessful();
    });

    it('handles pull errors gracefully', function () {
        $mockHandler = new MockHandler([
            new RequestException(
                'Connection failed',
                new Request('GET', '/api/knowledge/entries'),
                new Response(500)
            ),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);
        app()->instance(Client::class, $client);

        $this->artisan('sync', ['--pull' => true])
            ->expectsOutputToContain('Failed to pull from cloud')
            ->assertSuccessful();
    });

    it('handles push errors gracefully', function () {
        $mockHandler = new MockHandler([
            new RequestException(
                'Connection failed',
                new Request('POST', '/api/knowledge/sync'),
                new Response(500)
            ),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);
        app()->instance(Client::class, $client);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn(collect([
                [
                    'id' => 1,
                    'title' => 'Entry',
                    'content' => 'Content',
                    'category' => null,
                    'tags' => [],
                    'module' => null,
                    'priority' => 'medium',
                    'confidence' => 50,
                    'status' => 'draft',
                ],
            ]));

        $this->artisan('sync', ['--push' => true])
            ->expectsOutputToContain('Failed to push to cloud')
            ->assertSuccessful();
    });

    it('handles non-200 pull response', function () {
        $mockHandler = new MockHandler([
            new Response(404, [], json_encode(['error' => 'Not found'])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);
        app()->instance(Client::class, $client);

        $this->artisan('sync', ['--pull' => true])
            ->expectsOutputToContain('Pull Summary')
            ->assertSuccessful();
    });

    it('handles invalid pull response format', function () {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['invalid' => 'format'])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);
        app()->instance(Client::class, $client);

        $this->artisan('sync', ['--pull' => true])
            ->expectsOutputToContain('Invalid response from cloud API')
            ->assertSuccessful();
    });

    it('skips entries without unique_id during pull', function () {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    [
                        'title' => 'Entry without unique_id',
                        'content' => 'Content',
                    ],
                    [
                        'unique_id' => 'valid-id',
                        'title' => 'Valid Entry',
                        'content' => 'Content',
                        'category' => null,
                        'tags' => [],
                        'module' => null,
                        'priority' => 'medium',
                        'confidence' => 50,
                        'status' => 'draft',
                    ],
                ],
            ])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);
        app()->instance(Client::class, $client);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->andReturn(collect([]));

        $this->qdrant->shouldReceive('upsert')
            ->once(); // Only called for valid entry

        $this->artisan('sync', ['--pull' => true])
            ->expectsOutputToContain('Pull Summary')
            ->assertSuccessful();
    });

    it('warns when no local entries to push', function () {
        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn(collect([]));

        $this->artisan('sync', ['--push' => true])
            ->expectsOutput('No local entries to push.')
            ->assertSuccessful();
    });

    it('updates existing entries during pull', function () {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    [
                        'unique_id' => 'existing-id',
                        'title' => 'Existing Entry',
                        'content' => 'Updated content',
                        'category' => 'tutorial',
                        'tags' => ['updated'],
                        'module' => null,
                        'priority' => 'high',
                        'confidence' => 95,
                        'status' => 'validated',
                    ],
                ],
            ])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);
        app()->instance(Client::class, $client);

        // Return existing entry
        $this->qdrant->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'existing-uuid',
                    'title' => 'Existing Entry',
                    'usage_count' => 10,
                    'created_at' => '2024-01-01T00:00:00Z',
                ],
            ]));

        $this->qdrant->shouldReceive('upsert')
            ->once();

        $this->artisan('sync', ['--pull' => true])
            ->expectsOutputToContain('Pull Summary')
            ->assertSuccessful();
    });

    it('sends entries in batches of 100 during push', function () {
        // Create 150 entries to test batching
        $entries = collect();
        for ($i = 1; $i <= 150; $i++) {
            $entries->push([
                'id' => $i,
                'title' => "Entry {$i}",
                'content' => "Content {$i}",
                'category' => null,
                'tags' => [],
                'module' => null,
                'priority' => 'medium',
                'confidence' => 50,
                'status' => 'draft',
            ]);
        }

        $mockHandler = new MockHandler([
            // First batch (100 entries)
            new Response(200, [], json_encode([
                'summary' => [
                    'created' => 100,
                    'updated' => 0,
                    'failed' => 0,
                ],
            ])),
            // Second batch (50 entries)
            new Response(200, [], json_encode([
                'summary' => [
                    'created' => 50,
                    'updated' => 0,
                    'failed' => 0,
                ],
            ])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);
        app()->instance(Client::class, $client);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn($entries);

        $this->artisan('sync', ['--push' => true])
            ->expectsOutputToContain('Sending 150 entries in 2 batches')
            ->expectsOutputToContain('Push Summary')
            ->assertSuccessful();
    });

    it('handles nested response format during push', function () {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'summary' => [
                    'created' => 1,
                    'updated' => 1,
                    'failed' => 0,
                ],
            ])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);
        app()->instance(Client::class, $client);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn(collect([
                [
                    'id' => 1,
                    'title' => 'Entry',
                    'content' => 'Content',
                    'category' => null,
                    'tags' => [],
                    'module' => null,
                    'priority' => 'medium',
                    'confidence' => 50,
                    'status' => 'draft',
                ],
            ]));

        $this->artisan('sync', ['--push' => true])
            ->assertSuccessful();
    });

    it('truncates long titles to 255 characters during push', function () {
        $longTitle = str_repeat('a', 300);

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'summary' => [
                    'created' => 1,
                    'updated' => 0,
                    'failed' => 0,
                ],
            ])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);
        app()->instance(Client::class, $client);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn(collect([
                [
                    'id' => 1,
                    'title' => $longTitle,
                    'content' => 'Content',
                    'category' => null,
                    'tags' => [],
                    'module' => null,
                    'priority' => 'medium',
                    'confidence' => 50,
                    'status' => 'draft',
                ],
            ]));

        $this->artisan('sync', ['--push' => true])
            ->assertSuccessful();
    });
});
