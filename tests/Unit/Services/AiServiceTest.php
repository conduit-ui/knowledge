<?php

declare(strict_types=1);

use App\Services\AiService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

beforeEach(function (): void {
    config(['search.ollama.enabled' => true]);
    config(['search.ollama.host' => 'localhost']);
    config(['search.ollama.port' => 11434]);
    config(['search.ollama.model' => 'llama3.2:3b']);
    config(['search.ollama.timeout' => 30]);
});

afterEach(function (): void {
    app()->forgetInstance(Client::class);
});

describe('AiService configuration', function (): void {
    it('reports enabled when config is true', function (): void {
        $service = new AiService;

        expect($service->isEnabled())->toBeTrue();
    });

    it('reports disabled when config is false', function (): void {
        config(['search.ollama.enabled' => false]);
        $service = new AiService;

        expect($service->isEnabled())->toBeFalse();
    });
});

describe('AiService availability', function (): void {
    it('reports available when AI responds', function (): void {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['models' => []])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new AiService;

        expect($service->isAvailable())->toBeTrue();
    });

    it('reports unavailable on connection error', function (): void {
        $mockHandler = new MockHandler([
            new Response(500, [], 'Internal Server Error'),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new AiService;

        expect($service->isAvailable())->toBeFalse();
    });

    it('reports unavailable when disabled', function (): void {
        config(['search.ollama.enabled' => false]);
        $service = new AiService;

        expect($service->isAvailable())->toBeFalse();
    });
});

describe('AiService generate', function (): void {
    it('generates a response from AI', function (): void {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['response' => 'Hello, world!'])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new AiService;
        $result = $service->generate('test prompt');

        expect($result)->toBe('Hello, world!');
    });

    it('returns empty string on HTTP error', function (): void {
        $mockHandler = new MockHandler([
            new Response(500, [], 'Error'),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new AiService;
        $result = $service->generate('test prompt');

        expect($result)->toBe('');
    });

    it('returns empty string on invalid response', function (): void {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['invalid' => 'data'])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new AiService;
        $result = $service->generate('test prompt');

        expect($result)->toBe('');
    });
});

describe('AiService enhance', function (): void {
    it('enhances an entry with valid JSON response', function (): void {
        $jsonResponse = json_encode([
            'tags' => ['php', 'laravel', 'testing'],
            'category' => 'testing',
            'concepts' => ['unit testing', 'code coverage'],
            'summary' => 'A guide to PHP testing with Laravel.',
        ]);

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['response' => $jsonResponse])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new AiService;
        $result = $service->enhance([
            'title' => 'PHP Testing Guide',
            'content' => 'A comprehensive guide to testing PHP applications.',
        ]);

        expect($result['tags'])->toBe(['php', 'laravel', 'testing']);
        expect($result['category'])->toBe('testing');
        expect($result['concepts'])->toBe(['unit testing', 'code coverage']);
        expect($result['summary'])->toBe('A guide to PHP testing with Laravel.');
    });

    it('returns defaults on empty response', function (): void {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['response' => ''])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new AiService;
        $result = $service->enhance([
            'title' => 'Test',
            'content' => 'Content',
        ]);

        expect($result['tags'])->toBe([]);
        expect($result['category'])->toBeNull();
        expect($result['concepts'])->toBe([]);
        expect($result['summary'])->toBe('');
    });

    it('returns defaults on invalid JSON response', function (): void {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['response' => 'not valid json at all'])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new AiService;
        $result = $service->enhance([
            'title' => 'Test',
            'content' => 'Content',
        ]);

        expect($result['tags'])->toBe([]);
        expect($result['category'])->toBeNull();
        expect($result['concepts'])->toBe([]);
        expect($result['summary'])->toBe('');
    });

    it('rejects invalid category from AI response', function (): void {
        $jsonResponse = json_encode([
            'tags' => ['php'],
            'category' => 'invalid-category',
            'concepts' => ['concept'],
            'summary' => 'A summary.',
        ]);

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['response' => $jsonResponse])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new AiService;
        $result = $service->enhance([
            'title' => 'Test',
            'content' => 'Content',
            'category' => 'debugging',
        ]);

        expect($result['category'])->toBe('debugging');
    });

    it('preserves existing category when AI returns invalid one', function (): void {
        $jsonResponse = json_encode([
            'tags' => ['php'],
            'category' => 'not-valid',
            'concepts' => [],
            'summary' => '',
        ]);

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['response' => $jsonResponse])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new AiService;
        $result = $service->enhance([
            'title' => 'Test',
            'content' => 'Content',
        ]);

        expect($result['category'])->toBeNull();
    });

    it('handles JSON embedded in text', function (): void {
        $jsonResponse = 'Here is the analysis: {"tags": ["docker"], "category": "deployment", "concepts": ["containers"], "summary": "Docker guide."} Hope that helps!';

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['response' => $jsonResponse])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new AiService;
        $result = $service->enhance([
            'title' => 'Docker Guide',
            'content' => 'How to use Docker.',
        ]);

        expect($result['tags'])->toBe(['docker']);
        expect($result['category'])->toBe('deployment');
        expect($result['summary'])->toBe('Docker guide.');
    });
});
