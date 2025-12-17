<?php

declare(strict_types=1);

use App\Models\Collection;

describe('knowledge:collection:create command', function (): void {
    it('creates a collection with name only', function (): void {
        $this->artisan('collection:create', ['name' => 'My Collection'])
            ->expectsOutput('Collection "My Collection" created successfully.')
            ->assertExitCode(0);

        $collection = Collection::where('name', 'My Collection')->first();
        expect($collection)->not->toBeNull();
        expect($collection->name)->toBe('My Collection');
        expect($collection->description)->toBeNull();
    });

    it('creates a collection with description', function (): void {
        $this->artisan('collection:create', [
            'name' => 'Test Collection',
            '--description' => 'This is a test description',
        ])
            ->expectsOutput('Collection "Test Collection" created successfully.')
            ->assertExitCode(0);

        $collection = Collection::where('name', 'Test Collection')->first();
        expect($collection->description)->toBe('This is a test description');
    });

    it('prevents creating duplicate collection names', function (): void {
        Collection::factory()->create(['name' => 'Existing Collection']);

        $this->artisan('collection:create', ['name' => 'Existing Collection'])
            ->expectsOutput('Error: Collection "Existing Collection" already exists.')
            ->assertExitCode(1);

        expect(Collection::where('name', 'Existing Collection')->count())->toBe(1);
    });

    it('shows collection ID after creation', function (): void {
        $this->artisan('collection:create', ['name' => 'New Collection'])
            ->expectsOutputToContain('ID:')
            ->assertExitCode(0);
    });
});
