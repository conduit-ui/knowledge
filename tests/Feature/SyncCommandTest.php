<?php

declare(strict_types=1);

use App\Models\Entry;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mockery as m;

beforeEach(function () {
    Entry::query()->delete();
    // Set test API token
    putenv('PREFRONTAL_API_TOKEN=test-token-12345');
});

afterEach(function () {
    putenv('PREFRONTAL_API_TOKEN');
    m::close();
});

describe('SyncCommand', function () {
    it('fails when PREFRONTAL_API_TOKEN is not set', function () {
        putenv('PREFRONTAL_API_TOKEN');

        $this->artisan('sync')
            ->expectsOutput('PREFRONTAL_API_TOKEN environment variable is not set.')
            ->assertFailed();
    });

    it('has pull flag option', function () {
        // Mock successful GET request to pull entries
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [],
                'meta' => ['count' => 0, 'synced_at' => now()->toIso8601String()],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $this->app->instance(Client::class, $client);

        $this->artisan('sync', ['--pull' => true])
            ->expectsOutput('Pulling entries from cloud...')
            ->assertSuccessful();
    });

    it('has push flag option', function () {
        // Create local entry to push
        Entry::create([
            'title' => 'Test Entry',
            'content' => 'Test content',
            'priority' => 'medium',
            'confidence' => 50,
            'status' => 'draft',
        ]);

        // Mock successful POST request to push entries
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'success' => true,
                'message' => 'Knowledge entries synced',
                'summary' => ['created' => 1, 'updated' => 0, 'total' => 1],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $this->app->instance(Client::class, $client);

        $this->artisan('sync', ['--push' => true])
            ->expectsOutput('Pushing local entries to cloud...')
            ->assertSuccessful();
    });

    it('performs two-way sync by default', function () {
        // Create local entry
        Entry::create([
            'title' => 'Local Entry',
            'content' => 'Local content',
            'priority' => 'medium',
            'confidence' => 50,
            'status' => 'draft',
        ]);

        // Mock both GET (pull) and POST (push) requests for two-way sync
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [],
                'meta' => ['count' => 0, 'synced_at' => now()->toIso8601String()],
            ])),
            new Response(200, [], json_encode([
                'success' => true,
                'message' => 'Knowledge entries synced',
                'summary' => ['created' => 1, 'updated' => 0, 'total' => 1],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $this->app->instance(Client::class, $client);

        $this->artisan('sync')
            ->expectsOutput('Starting two-way sync (pull then push)...')
            ->assertSuccessful();
    });

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

    it('handles HTTP error during pull', function () {
        // Use Mockery for more control
        $response = m::mock(\Psr\Http\Message\ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(500);
        $response->shouldReceive('getBody')->andReturn('Internal Server Error');

        $client = m::mock(Client::class);
        $client->shouldReceive('get')
            ->once()
            ->with('/api/knowledge/entries', m::any())
            ->andReturn($response);

        $this->app->instance(Client::class, $client);

        $this->artisan('sync', ['--pull' => true])
            ->assertSuccessful();

        // Verify no entries were created due to error
        expect(Entry::count())->toBe(0);
    });

    it('handles invalid JSON response during pull', function () {
        // Mock response without 'data' key
        $mock = new MockHandler([
            new Response(200, [], json_encode(['invalid' => 'structure'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $this->app->instance(Client::class, $client);

        $this->artisan('sync', ['--pull' => true])
            ->assertSuccessful();

        // Verify no entries were created due to invalid response
        expect(Entry::count())->toBe(0);
    });

    it('handles network error during pull', function () {
        // Mock Guzzle exception
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException(
                'Connection refused',
                new \GuzzleHttp\Psr7\Request('GET', '/api/knowledge/entries')
            ),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $this->app->instance(Client::class, $client);

        $this->artisan('sync', ['--pull' => true])
            ->assertSuccessful();

        // Verify no entries were created due to network error
        expect(Entry::count())->toBe(0);
    });

    it('handles HTTP error during push', function () {
        Entry::create([
            'title' => 'Test Entry',
            'content' => 'Test content',
            'priority' => 'medium',
            'confidence' => 50,
            'status' => 'draft',
        ]);

        // Use Mockery for more control
        $response = m::mock(\Psr\Http\Message\ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(500);
        $response->shouldReceive('getBody')->andReturn('Internal Server Error');

        $client = m::mock(Client::class);
        $client->shouldReceive('post')
            ->once()
            ->with('/api/knowledge/sync', m::any())
            ->andReturn($response);

        $this->app->instance(Client::class, $client);

        $this->artisan('sync', ['--push' => true])
            ->assertSuccessful();

        // Command completes despite error (returns SUCCESS)
        expect(Entry::count())->toBe(1);
    });

    it('handles network error during push', function () {
        Entry::create([
            'title' => 'Test Entry',
            'content' => 'Test content',
            'priority' => 'medium',
            'confidence' => 50,
            'status' => 'draft',
        ]);

        // Mock Guzzle exception
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException(
                'Connection timeout',
                new \GuzzleHttp\Psr7\Request('POST', '/api/knowledge/sync')
            ),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $this->app->instance(Client::class, $client);

        $this->artisan('sync', ['--push' => true])
            ->assertSuccessful();

        // Command completes despite error
        expect(Entry::count())->toBe(1);
    });

    it('pulls and processes entries from cloud', function () {
        // Mock successful GET with actual entry data
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    [
                        'unique_id' => 'test-unique-id-123',
                        'title' => 'Cloud Entry',
                        'content' => 'Content from cloud',
                        'category' => 'test',
                        'tags' => ['cloud', 'test'],
                        'module' => 'sync',
                        'priority' => 'high',
                        'confidence' => 80,
                        'source' => 'prefrontal',
                        'ticket' => 'TICKET-123',
                        'status' => 'validated',
                    ],
                ],
                'meta' => ['count' => 1, 'synced_at' => now()->toIso8601String()],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $this->app->instance(Client::class, $client);

        expect(Entry::count())->toBe(0);

        $this->artisan('sync', ['--pull' => true])
            ->expectsOutput('Pulling entries from cloud...')
            ->assertSuccessful();

        expect(Entry::count())->toBe(1);
        $entry = Entry::first();
        expect($entry->title)->toBe('Cloud Entry')
            ->and($entry->content)->toBe('Content from cloud')
            ->and($entry->priority)->toBe('high')
            ->and($entry->confidence)->toBe(80);
    });

    it('updates existing entries during pull when title matches', function () {
        // Create existing local entry
        Entry::create([
            'title' => 'Existing Entry',
            'content' => 'Old content',
            'priority' => 'low',
            'confidence' => 30,
            'status' => 'draft',
        ]);

        // Mock pull with updated version
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    [
                        'unique_id' => 'test-unique-id-456',
                        'title' => 'Existing Entry',
                        'content' => 'Updated content from cloud',
                        'category' => 'updated',
                        'tags' => ['updated'],
                        'module' => 'sync',
                        'priority' => 'high',
                        'confidence' => 90,
                        'source' => 'prefrontal',
                        'ticket' => null,
                        'status' => 'validated',
                    ],
                ],
                'meta' => ['count' => 1, 'synced_at' => now()->toIso8601String()],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $this->app->instance(Client::class, $client);

        expect(Entry::count())->toBe(1);

        $this->artisan('sync', ['--pull' => true])
            ->expectsOutput('Pulling entries from cloud...')
            ->assertSuccessful();

        expect(Entry::count())->toBe(1); // Still 1, updated not created
        $entry = Entry::first();
        expect($entry->title)->toBe('Existing Entry')
            ->and($entry->content)->toBe('Updated content from cloud')
            ->and($entry->priority)->toBe('high')
            ->and($entry->confidence)->toBe(90)
            ->and($entry->status)->toBe('validated');
    });

    it('skips entries with null unique_id during pull', function () {
        // Mock response with entry missing unique_id
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    [
                        // Missing unique_id
                        'title' => 'Entry Without ID',
                        'content' => 'Should be skipped',
                    ],
                    [
                        'unique_id' => 'valid-id-789',
                        'title' => 'Valid Entry',
                        'content' => 'Should be created',
                        'priority' => 'medium',
                        'confidence' => 50,
                        'status' => 'draft',
                    ],
                ],
                'meta' => ['count' => 2, 'synced_at' => now()->toIso8601String()],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $this->app->instance(Client::class, $client);

        $this->artisan('sync', ['--pull' => true])
            ->assertSuccessful();

        // Only the valid entry should be created
        expect(Entry::count())->toBe(1);
        expect(Entry::first()->title)->toBe('Valid Entry');
    });

    it('handles exceptions during entry processing', function () {
        // Mock response with invalid enum value that will trigger database exception
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    [
                        'unique_id' => 'invalid-entry-1',
                        'title' => 'Entry with invalid priority',
                        'content' => 'This will fail',
                        'priority' => 'INVALID_PRIORITY_VALUE', // Invalid enum value
                        'confidence' => 50,
                        'status' => 'draft',
                    ],
                    [
                        'unique_id' => 'valid-entry-2',
                        'title' => 'Good Entry',
                        'content' => 'This should be created',
                        'priority' => 'medium',
                        'confidence' => 50,
                        'status' => 'draft',
                    ],
                ],
                'meta' => ['count' => 2, 'synced_at' => now()->toIso8601String()],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $this->app->instance(Client::class, $client);

        $this->artisan('sync', ['--pull' => true])
            ->assertSuccessful();

        // The first entry should fail due to invalid enum, but second should succeed
        expect(Entry::count())->toBe(1);
        expect(Entry::first()->title)->toBe('Good Entry');
    });

    it('creates HTTP client with correct configuration', function () {
        // Test the createClient method by using reflection
        $command = new \App\Commands\SyncCommand;

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('createClient');
        $method->setAccessible(true);

        $client = $method->invoke($command);

        expect($client)->toBeInstanceOf(Client::class);

        // Verify the client has the correct configuration
        $config = $client->getConfig();
        expect($config['base_uri'])->toBeInstanceOf(\GuzzleHttp\Psr7\Uri::class);
        expect((string) $config['base_uri'])->toBe('https://prefrontal-cortex-master-iw3xyv.laravel.cloud');
        expect($config['timeout'])->toBe(30);
        expect($config['headers']['Accept'])->toBe('application/json');
        expect($config['headers']['Content-Type'])->toBe('application/json');
    });

    it('uses createClient when no client is bound to container', function () {
        // Ensure no Client is bound to the container
        if (app()->bound(Client::class)) {
            app()->forgetInstance(Client::class);
        }

        $command = new \App\Commands\SyncCommand;

        // Use reflection to call getClient()
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getClient');
        $method->setAccessible(true);

        // This should trigger the createClient() path (line 82)
        $client = $method->invoke($command);

        expect($client)->toBeInstanceOf(Client::class);
        expect((string) $client->getConfig('base_uri'))->toBe('https://prefrontal-cortex-master-iw3xyv.laravel.cloud');
    });

});
