<?php

declare(strict_types=1);

use App\Models\Entry;

it('validates an entry and boosts confidence', function () {
    $entry = Entry::factory()->create([
        'title' => 'Test Entry',
        'confidence' => 80,
        'status' => 'draft',
        'validation_date' => null,
    ]);

    $this->artisan('validate', ['id' => $entry->id])
        ->assertSuccessful()
        ->expectsOutputToContain("Entry #{$entry->id} validated successfully!")
        ->expectsOutputToContain('Title: Test Entry')
        ->expectsOutputToContain('Status: draft -> validated')
        ->expectsOutputToContain('Confidence: 80% -> 96%')
        ->expectsOutputToContain('The entry has been marked as validated and its confidence has been updated.');

    $entry->refresh();
    expect($entry->status)->toBe('validated')
        ->and($entry->validation_date)->not->toBeNull()
        ->and($entry->confidence)->toBe(96);
});

it('shows error when entry not found', function () {
    $this->artisan('validate', ['id' => 9999])
        ->assertFailed()
        ->expectsOutput('Entry not found with ID: 9999');
});

it('validates id must be numeric', function () {
    $this->artisan('validate', ['id' => 'abc'])
        ->assertFailed()
        ->expectsOutput('Entry ID must be a number.');
});

it('validates entry that is already validated', function () {
    $entry = Entry::factory()->create([
        'title' => 'Already Validated',
        'confidence' => 85,
        'status' => 'validated',
        'validation_date' => now()->subDays(10),
    ]);

    $this->artisan('validate', ['id' => $entry->id])
        ->assertSuccessful()
        ->expectsOutputToContain('Status: validated -> validated');
});

it('displays validation date after validation', function () {
    $entry = Entry::factory()->create([
        'confidence' => 70,
        'status' => 'draft',
    ]);

    $this->artisan('validate', ['id' => $entry->id])
        ->assertSuccessful()
        ->expectsOutputToContain('Validation Date:');

    $entry->refresh();
    expect($entry->validation_date)->not->toBeNull();
});

it('validates entry with high confidence', function () {
    $entry = Entry::factory()->create([
        'title' => 'High Confidence Entry',
        'confidence' => 95,
        'status' => 'draft',
    ]);

    $this->artisan('validate', ['id' => $entry->id])
        ->assertSuccessful()
        ->expectsOutputToContain('Confidence: 95% -> 100%'); // Capped at 100

    $entry->refresh();
    expect($entry->confidence)->toBe(100);
});

it('validates entry with low confidence', function () {
    $entry = Entry::factory()->create([
        'confidence' => 50,
        'status' => 'draft',
    ]);

    $this->artisan('validate', ['id' => $entry->id])
        ->assertSuccessful()
        ->expectsOutputToContain('Confidence: 50% -> 60%');

    $entry->refresh();
    expect($entry->confidence)->toBe(60);
});
