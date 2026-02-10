<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;

class EntryMetadataService
{
    private const STALE_THRESHOLD_DAYS = 90;

    private const DEGRADATION_RATE_PER_DAY = 0.15;

    private const MIN_CONFIDENCE = 10;

    /**
     * Check if an entry is stale (not verified within threshold days).
     *
     * @param  array{last_verified?: string|null, created_at?: string}  $entry
     */
    public function isStale(array $entry): bool
    {
        $referenceDate = $this->getVerificationDate($entry);

        if ($referenceDate === null) {
            return true;
        }

        return $referenceDate->diffInDays(now()) >= self::STALE_THRESHOLD_DAYS;
    }

    /**
     * Get the number of days since last verification.
     *
     * @param  array{last_verified?: string|null, created_at?: string}  $entry
     */
    public function daysSinceVerification(array $entry): int
    {
        $referenceDate = $this->getVerificationDate($entry);

        if ($referenceDate === null) {
            return self::STALE_THRESHOLD_DAYS;
        }

        return (int) $referenceDate->diffInDays(now());
    }

    /**
     * Calculate degraded confidence based on time since last verification.
     *
     * Confidence degrades by DEGRADATION_RATE_PER_DAY per day after the stale threshold.
     * Never drops below MIN_CONFIDENCE.
     *
     * @param  array{confidence?: int, last_verified?: string|null, created_at?: string}  $entry
     */
    public function calculateEffectiveConfidence(array $entry): int
    {
        $baseConfidence = $entry['confidence'] ?? 0;
        $daysSince = $this->daysSinceVerification($entry);

        if ($daysSince < self::STALE_THRESHOLD_DAYS) {
            return $baseConfidence;
        }

        $daysOverThreshold = $daysSince - self::STALE_THRESHOLD_DAYS;
        $degradation = (int) round($daysOverThreshold * self::DEGRADATION_RATE_PER_DAY);
        $effective = max(self::MIN_CONFIDENCE, $baseConfidence - $degradation);

        return $effective;
    }

    /**
     * Map a numeric confidence (0-100) to a confidence level string.
     */
    public function confidenceLevel(int $confidence): string
    {
        return match (true) {
            $confidence >= 70 => 'high',
            $confidence >= 40 => 'medium',
            default => 'low',
        };
    }

    /**
     * Get the stale threshold in days.
     */
    public function getStaleThresholdDays(): int
    {
        return self::STALE_THRESHOLD_DAYS;
    }

    /**
     * Get the verification reference date for an entry.
     *
     * @param  array{last_verified?: string|null, created_at?: string}  $entry
     */
    private function getVerificationDate(array $entry): ?Carbon
    {
        $lastVerified = $entry['last_verified'] ?? null;

        if (is_string($lastVerified) && $lastVerified !== '') {
            return Carbon::parse($lastVerified);
        }

        $createdAt = $entry['created_at'] ?? null;

        if (is_string($createdAt) && $createdAt !== '') {
            return Carbon::parse($createdAt);
        }

        return null;
    }
}
