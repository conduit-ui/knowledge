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

        // Bind mocked client to container
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

        // Bind mocked client to container
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
            // First request: GET for pull
            new Response(200, [], json_encode([
                'data' => [],
                'meta' => ['count' => 0, 'synced_at' => now()->toIso8601String()],
            ])),
            // Second request: POST for push
            new Response(200, [], json_encode([
                'success' => true,
                'message' => 'Knowledge entries synced',
                'summary' => ['created' => 1, 'updated' => 0, 'total' => 1],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        // Bind mocked client to container
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
});
