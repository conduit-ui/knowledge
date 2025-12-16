<?php

declare(strict_types=1);

use App\Models\Entry;

describe('KnowledgeValidateCommand', function () {
    it('validates an entry by id', function () {
        $entry = Entry::factory()->create([
            'status' => 'draft',
            'confidence' => 80,
            'validation_date' => null,
        ]);

        $this->artisan('knowledge:validate', ['id' => $entry->id])
            ->expectsOutputToContain('validated successfully')
            ->assertSuccessful();

        $fresh = $entry->fresh();
        expect($fresh->status)->toBe('validated')
            ->and($fresh->validation_date)->not->toBeNull();
    });

    it('boosts confidence when validating', function () {
        $entry = Entry::factory()->create([
            'status' => 'draft',
            'confidence' => 80,
            'validation_date' => null,
            'created_at' => now(),
        ]);

        $this->artisan('knowledge:validate', ['id' => $entry->id])
            ->assertSuccessful();

        $fresh = $entry->fresh();
        expect($fresh->confidence)->toBe(96);
    });

    it('displays updated confidence', function () {
        $entry = Entry::factory()->create([
            'status' => 'draft',
            'confidence' => 80,
            'validation_date' => null,
            'created_at' => now(),
        ]);

        $this->artisan('knowledge:validate', ['id' => $entry->id])
            ->expectsOutputToContain('Confidence: 80% -> 96%')
            ->assertSuccessful();
    });

    it('fails when entry not found', function () {
        $this->artisan('knowledge:validate', ['id' => 999])
            ->expectsOutputToContain('Entry not found')
            ->assertFailed();
    });

    it('can validate already validated entry', function () {
        $entry = Entry::factory()->create([
            'status' => 'validated',
            'confidence' => 90,
            'validation_date' => now()->subDays(30),
        ]);

        $oldValidationDate = $entry->validation_date;

        $this->artisan('knowledge:validate', ['id' => $entry->id])
            ->assertSuccessful();

        $fresh = $entry->fresh();
        expect($fresh->status)->toBe('validated')
            ->and($fresh->validation_date->gt($oldValidationDate))->toBeTrue();
    });
});
