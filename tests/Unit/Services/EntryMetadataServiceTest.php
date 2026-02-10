<?php

declare(strict_types=1);

use App\Services\EntryMetadataService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->service = new EntryMetadataService;
});

describe('isStale', function (): void {
    it('returns true when entry has no last_verified and no created_at', function (): void {
        $entry = [];

        expect($this->service->isStale($entry))->toBeTrue();
    });

    it('returns false when entry was verified recently', function (): void {
        $entry = ['last_verified' => now()->subDays(30)->toIso8601String()];

        expect($this->service->isStale($entry))->toBeFalse();
    });

    it('returns true when entry was verified more than 90 days ago', function (): void {
        $entry = ['last_verified' => now()->subDays(91)->toIso8601String()];

        expect($this->service->isStale($entry))->toBeTrue();
    });

    it('returns true when entry was verified exactly 90 days ago', function (): void {
        $entry = ['last_verified' => now()->subDays(90)->toIso8601String()];

        expect($this->service->isStale($entry))->toBeTrue();
    });

    it('returns false when entry was verified 89 days ago', function (): void {
        $entry = ['last_verified' => now()->subDays(89)->toIso8601String()];

        expect($this->service->isStale($entry))->toBeFalse();
    });

    it('falls back to created_at when last_verified is null', function (): void {
        $entry = [
            'last_verified' => null,
            'created_at' => now()->subDays(30)->toIso8601String(),
        ];

        expect($this->service->isStale($entry))->toBeFalse();
    });

    it('considers entry stale when created_at is old and no last_verified', function (): void {
        $entry = [
            'last_verified' => null,
            'created_at' => now()->subDays(100)->toIso8601String(),
        ];

        expect($this->service->isStale($entry))->toBeTrue();
    });
});

describe('daysSinceVerification', function (): void {
    it('returns days since last verification', function (): void {
        Carbon::setTestNow('2026-02-10');

        $entry = ['last_verified' => '2026-01-11T00:00:00+00:00'];

        expect($this->service->daysSinceVerification($entry))->toBe(30);

        Carbon::setTestNow();
    });

    it('returns threshold days when no dates available', function (): void {
        $entry = [];

        expect($this->service->daysSinceVerification($entry))->toBe(90);
    });

    it('falls back to created_at', function (): void {
        Carbon::setTestNow('2026-02-10');

        $entry = [
            'last_verified' => null,
            'created_at' => '2026-01-31T00:00:00+00:00',
        ];

        expect($this->service->daysSinceVerification($entry))->toBe(10);

        Carbon::setTestNow();
    });
});

describe('calculateEffectiveConfidence', function (): void {
    it('returns base confidence when not stale', function (): void {
        $entry = [
            'confidence' => 80,
            'last_verified' => now()->subDays(30)->toIso8601String(),
        ];

        expect($this->service->calculateEffectiveConfidence($entry))->toBe(80);
    });

    it('degrades confidence after stale threshold', function (): void {
        $entry = [
            'confidence' => 80,
            'last_verified' => now()->subDays(110)->toIso8601String(),
        ];

        // 20 days over threshold * 0.15 = 3 degradation
        expect($this->service->calculateEffectiveConfidence($entry))->toBe(77);
    });

    it('never drops below minimum confidence', function (): void {
        $entry = [
            'confidence' => 20,
            'last_verified' => now()->subDays(500)->toIso8601String(),
        ];

        expect($this->service->calculateEffectiveConfidence($entry))->toBe(10);
    });

    it('handles zero confidence', function (): void {
        $entry = [
            'confidence' => 0,
            'last_verified' => now()->subDays(200)->toIso8601String(),
        ];

        expect($this->service->calculateEffectiveConfidence($entry))->toBe(10);
    });

    it('returns base confidence when exactly at threshold', function (): void {
        Carbon::setTestNow('2026-02-10');

        $entry = [
            'confidence' => 75,
            'last_verified' => now()->subDays(90)->toIso8601String(),
        ];

        // Exactly at threshold, 0 days over, no degradation
        expect($this->service->calculateEffectiveConfidence($entry))->toBe(75);

        Carbon::setTestNow();
    });
});

describe('confidenceLevel', function (): void {
    it('returns high for confidence >= 70', function (): void {
        expect($this->service->confidenceLevel(70))->toBe('high');
        expect($this->service->confidenceLevel(100))->toBe('high');
        expect($this->service->confidenceLevel(85))->toBe('high');
    });

    it('returns medium for confidence >= 40 and < 70', function (): void {
        expect($this->service->confidenceLevel(40))->toBe('medium');
        expect($this->service->confidenceLevel(69))->toBe('medium');
        expect($this->service->confidenceLevel(55))->toBe('medium');
    });

    it('returns low for confidence < 40', function (): void {
        expect($this->service->confidenceLevel(39))->toBe('low');
        expect($this->service->confidenceLevel(0))->toBe('low');
        expect($this->service->confidenceLevel(10))->toBe('low');
    });
});

describe('getStaleThresholdDays', function (): void {
    it('returns 90 days', function (): void {
        expect($this->service->getStaleThresholdDays())->toBe(90);
    });
});
