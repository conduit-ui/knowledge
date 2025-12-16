<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;

describe('knowledge:unlink command', function (): void {
    it('deletes a relationship', function (): void {
        $entry1 = Entry::factory()->create(['title' => 'Entry One']);
        $entry2 = Entry::factory()->create(['title' => 'Entry Two']);
        $relationship = Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
        ]);

        $this->artisan('knowledge:unlink', ['id' => $relationship->id])
            ->expectsQuestion('Are you sure you want to delete this relationship?', true)
            ->expectsOutputToContain('deleted successfully')
            ->assertSuccessful();

        expect(Relationship::find($relationship->id))->toBeNull();
    });

    it('shows relationship details before deletion', function (): void {
        $entry1 = Entry::factory()->create(['title' => 'Source Entry']);
        $entry2 = Entry::factory()->create(['title' => 'Target Entry']);
        $relationship = Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => Relationship::TYPE_DEPENDS_ON,
        ]);

        $this->artisan('knowledge:unlink', ['id' => $relationship->id])
            ->expectsQuestion('Are you sure you want to delete this relationship?', true)
            ->expectsOutputToContain('depends_on')
            ->expectsOutputToContain('Source Entry')
            ->expectsOutputToContain('Target Entry')
            ->assertSuccessful();
    });

    it('cancels deletion when user declines', function (): void {
        $relationship = Relationship::factory()->create();

        $this->artisan('knowledge:unlink', ['id' => $relationship->id])
            ->expectsQuestion('Are you sure you want to delete this relationship?', false)
            ->expectsOutputToContain('cancelled')
            ->assertSuccessful();

        expect(Relationship::find($relationship->id))->not->toBeNull();
    });

    it('fails when relationship does not exist', function (): void {
        $this->artisan('knowledge:unlink', ['id' => 99999])
            ->expectsOutputToContain('not found')
            ->assertFailed();
    });

    it('shows from and to entry IDs', function (): void {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();
        $relationship = Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
        ]);

        $this->artisan('knowledge:unlink', ['id' => $relationship->id])
            ->expectsQuestion('Are you sure you want to delete this relationship?', true)
            ->expectsOutputToContain("#{$entry1->id}")
            ->expectsOutputToContain("#{$entry2->id}")
            ->assertSuccessful();
    });

    it('fails when deletion fails', function (): void {
        $relationship = Relationship::factory()->create();

        // Mock the service to return false
        $mock = Mockery::mock(\App\Services\RelationshipService::class);
        $mock->shouldReceive('deleteRelationship')
            ->with($relationship->id)
            ->andReturn(false);

        $this->app->instance(\App\Services\RelationshipService::class, $mock);

        $this->artisan('knowledge:unlink', ['id' => $relationship->id])
            ->expectsQuestion('Are you sure you want to delete this relationship?', true)
            ->expectsOutputToContain('Failed to delete relationship')
            ->assertFailed();
    });
});
