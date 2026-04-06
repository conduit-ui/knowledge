<?php

declare(strict_types=1);

use Laravel\Mcp\Request;

uses()->group('providers');

describe('McpServiceProvider container callback', function (): void {
    it('copies arguments from mcp.request into newly resolved Request instances', function (): void {
        $mcpRequest = new Request(
            arguments: ['query' => 'hello world', 'project' => 'test'],
            sessionId: 'session-abc',
            meta: ['client' => 'test-client'],
        );

        app()->instance('mcp.request', $mcpRequest);

        $resolved = app(Request::class);

        expect($resolved->get('query'))->toBe('hello world')
            ->and($resolved->get('project'))->toBe('test')
            ->and($resolved->sessionId())->toBe('session-abc')
            ->and($resolved->meta())->toBe(['client' => 'test-client']);
    });

    it('does not modify resolved Request when mcp.request is not bound', function (): void {
        // Ensure no mcp.request binding exists
        if (app()->bound('mcp.request')) {
            app()->forgetInstance('mcp.request');
        }

        $resolved = new Request(['foo' => 'bar']);

        expect($resolved->get('foo'))->toBe('bar');
    });
});
