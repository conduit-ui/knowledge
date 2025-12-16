<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Services\ConfidenceService;

describe('ConfidenceService', function () {
    beforeEach(function () {
        $this->service = new ConfidenceService;
    });

    it('calculates confidence for fresh entry', function () {
        $entry = Entry::factory()->create([
            'confidence' => 80,
            'status' => 'draft',
            'validation_date' => null,
            'created_at' => now(),
        ]);

        $confidence = $this->service->calculateConfidence($entry);

        expect($confidence)->toBe(80);
    });

    it('applies age factor to older entries', function () {
        $entry = Entry::factory()->create([
            'confidence' => 80,
            'status' => 'draft',
            'validation_date' => null,
            'created_at' => now()->subDays(365),
        ]);

        $confidence = $this->service->calculateConfidence($entry);

        // age_factor = max(0.5, 1 - (365 / 365)) = max(0.5, 0) = 0.5
        // 80 * 0.5 = 40
        expect($confidence)->toBe(40);
    });

    it('applies validation boost to validated entries', function () {
        $entry = Entry::factory()->create([
            'confidence' => 80,
            'status' => 'validated',
            'validation_date' => now(),
            'created_at' => now(),
        ]);

        $confidence = $this->service->calculateConfidence($entry);

        // 80 * 1.0 * 1.2 = 96
        expect($confidence)->toBe(96);
    });

    it('caps confidence at 100', function () {
        $entry = Entry::factory()->create([
            'confidence' => 95,
            'status' => 'validated',
            'validation_date' => now(),
            'created_at' => now(),
        ]);

        $confidence = $this->service->calculateConfidence($entry);

        // 95 * 1.0 * 1.2 = 114, capped at 100
        expect($confidence)->toBe(100);
    });

    it('caps confidence at 0', function () {
        $entry = Entry::factory()->create([
            'confidence' => 10,
            'status' => 'draft',
            'validation_date' => null,
            'created_at' => now()->subDays(1000),
        ]);

        $confidence = $this->service->calculateConfidence($entry);

        expect($confidence)->toBeGreaterThanOrEqual(0);
    });

    it('combines age factor and validation boost', function () {
        $entry = Entry::factory()->create([
            'confidence' => 80,
            'status' => 'validated',
            'validation_date' => now(),
            'created_at' => now()->subDays(182), // half a year
        ]);

        $confidence = $this->service->calculateConfidence($entry);

        // age_factor = max(0.5, 1 - (182 / 365)) = max(0.5, 0.5014) = 0.5014
        // 80 * 0.5014 * 1.2 = 48.13 (rounded to 48)
        expect($confidence)->toBe(48);
    });

    it('updates entry confidence', function () {
        $entry = Entry::factory()->create([
            'confidence' => 80,
            'status' => 'validated',
            'validation_date' => now(),
            'created_at' => now(),
        ]);

        $this->service->updateConfidence($entry);

        expect($entry->fresh()->confidence)->toBe(96);
    });

    it('validates entry and boosts confidence', function () {
        $entry = Entry::factory()->create([
            'confidence' => 80,
            'status' => 'draft',
            'validation_date' => null,
            'created_at' => now(),
        ]);

        $this->service->validateEntry($entry);

        $fresh = $entry->fresh();
        expect($fresh->status)->toBe('validated')
            ->and($fresh->validation_date)->not->toBeNull()
            ->and($fresh->confidence)->toBe(96);
    });

    it('identifies stale entries not used in 90 days', function () {
        Entry::factory()->create([
            'last_used' => now()->subDays(91),
            'confidence' => 80,
        ]);

        Entry::factory()->create([
            'last_used' => now()->subDays(89),
            'confidence' => 80,
        ]);

        Entry::factory()->create([
            'last_used' => null,
            'created_at' => now()->subDays(91),
            'confidence' => 80,
        ]);

        $staleEntries = $this->service->getStaleEntries();

        expect($staleEntries)->toHaveCount(2);
    });

    it('identifies high confidence old entries needing review', function () {
        Entry::factory()->create([
            'confidence' => 85,
            'status' => 'draft',
            'created_at' => now()->subDays(200),
            'last_used' => now()->subDays(50), // Used recently, so only matches high confidence old rule
        ]);

        Entry::factory()->create([
            'confidence' => 60,
            'status' => 'draft',
            'created_at' => now()->subDays(200),
            'last_used' => now()->subDays(50), // Used recently, doesn't match any stale criteria
        ]);

        Entry::factory()->create([
            'confidence' => 85,
            'status' => 'draft',
            'created_at' => now()->subDays(50),
            'last_used' => now()->subDays(10), // Too new to be stale
        ]);

        $staleEntries = $this->service->getStaleEntries();

        expect($staleEntries->where('confidence', '>=', 70)->where('status', '!=', 'validated'))
            ->toHaveCount(1);
    });
});
