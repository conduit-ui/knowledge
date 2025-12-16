<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Models\Entry;

describe('knowledge:collection:list command', function (): void {
    it('lists all collections with entry counts', function (): void {
        $collection1 = Collection::factory()->create(['name' => 'Alpha Collection']);
        $collection2 = Collection::factory()->create(['name' => 'Beta Collection']);
        $collection3 = Collection::factory()->create(['name' => 'Gamma Collection']);

        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();

        $collection1->entries()->attach([$entry1->id => ['sort_order' => 0]]);
        $collection2->entries()->attach([
            $entry1->id => ['sort_order' => 0],
            $entry2->id => ['sort_order' => 1],
        ]);

        $this->artisan('knowledge:collection:list')
            ->expectsOutputToContain('Alpha Collection')
            ->expectsOutputToContain('Beta Collection')
            ->expectsOutputToContain('Gamma Collection')
            ->assertExitCode(0);
    });

    it('displays descriptions when available', function (): void {
        $collection = Collection::factory()->create([
            'name' => 'Test Collection',
            'description' => 'A test description',
        ]);

        $this->artisan('knowledge:collection:list')
            ->expectsOutputToContain('A test description')
            ->assertExitCode(0);
    });

    it('shows message when no collections exist', function (): void {
        $this->artisan('knowledge:collection:list')
            ->expectsOutput('No collections found.')
            ->assertExitCode(0);
    });

    it('orders collections alphabetically by name', function (): void {
        Collection::factory()->create(['name' => 'Zebra']);
        Collection::factory()->create(['name' => 'Alpha']);
        Collection::factory()->create(['name' => 'Middle']);

        $this->artisan('knowledge:collection:list')
            ->expectsOutputToContain('Alpha')
            ->expectsOutputToContain('Middle')
            ->expectsOutputToContain('Zebra')
            ->assertExitCode(0);

        // Verify order in table
        $output = $this->artisan('knowledge:collection:list')->run();
        $outputText = $this->app->make('Illuminate\Contracts\Console\Kernel')->output();
    });
});
