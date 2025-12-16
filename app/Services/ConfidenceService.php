<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Entry;
use Illuminate\Database\Eloquent\Collection;

class ConfidenceService
{
    /**
     * Calculate final confidence score for an entry.
     *
     * Formula: final_confidence = initial_confidence * age_factor * validation_factor
     * - age_factor = max(0.5, 1 - (days_old / 365))
     * - validation_factor = validated ? 1.2 : 1.0
     * - Result capped at 0-100
     */
    public function calculateConfidence(Entry $entry): int
    {
        $initialConfidence = $entry->confidence;

        // Calculate age factor
        $daysOld = $entry->created_at->diffInDays(now());
        $ageFactor = max(0.5, 1 - ($daysOld / 365));

        // Calculate validation factor
        $validationFactor = $entry->status === 'validated' ? 1.2 : 1.0;

        // Calculate final confidence
        $finalConfidence = $initialConfidence * $ageFactor * $validationFactor;

        // Cap at 0-100
        return (int) max(0, min(100, round($finalConfidence)));
    }

    /**
     * Update the confidence score for an entry.
     */
    public function updateConfidence(Entry $entry): void
    {
        $newConfidence = $this->calculateConfidence($entry);
        $entry->update(['confidence' => $newConfidence]);
    }

    /**
     * Validate an entry and boost its confidence.
     */
    public function validateEntry(Entry $entry): void
    {
        $entry->update([
            'status' => 'validated',
            'validation_date' => now(),
        ]);

        $this->updateConfidence($entry);
    }

    /**
     * Get entries that are stale and need review.
     *
     * Criteria:
     * - Not used in 90+ days, OR
     * - Never used and created 90+ days ago, OR
     * - High confidence (>= 70) but old (180+ days) and not validated
     *
     * @return Collection<int, Entry>
     */
    public function getStaleEntries(): Collection
    {
        $ninetyDaysAgo = now()->subDays(90);
        $oneEightyDaysAgo = now()->subDays(180);

        $query = Entry::query()
            ->where(function ($q) use ($ninetyDaysAgo, $oneEightyDaysAgo) {
                // Not used in 90+ days
                $q->where('last_used', '<=', $ninetyDaysAgo)
                    // OR never used and old
                    ->orWhere(function ($subQuery) use ($ninetyDaysAgo) {
                        $subQuery->whereNull('last_used')
                            ->where('created_at', '<=', $ninetyDaysAgo);
                    })
                    // OR high confidence but old and not validated
                    ->orWhere(function ($subQuery) use ($oneEightyDaysAgo) {
                        $subQuery->where('confidence', '>=', 70)
                            ->where('created_at', '<=', $oneEightyDaysAgo)
                            ->where('status', '!=', 'validated');
                    });
            })
            ->orderBy('last_used', 'asc')
            ->orderBy('created_at', 'asc');

        /** @var Collection<int, Entry> */
        return $query->get();
    }
}
