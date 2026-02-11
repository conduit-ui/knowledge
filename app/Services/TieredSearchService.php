<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SearchTier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TieredSearchService
{
    private const CONFIDENCE_THRESHOLD = 0.75;

    private const RECENT_DAYS = 14;

    private const FRESHNESS_HALF_LIFE_DAYS = 30.0;

    public function __construct(
        private readonly QdrantService $qdrantService,
        private readonly EntryMetadataService $metadataService,
    ) {}

    /**
     * Search with tiered narrow-to-wide retrieval.
     *
     * Returns early if confident matches are found at a tier.
     *
     * @param  array<string, string>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function search(
        string $query,
        array $filters = [],
        int $limit = 20,
        ?SearchTier $forceTier = null,
        string $project = 'default',
    ): Collection {
        if ($forceTier !== null) {
            return $this->searchTier($query, $filters, $limit, $forceTier, $project);
        }

        foreach (SearchTier::searchOrder() as $tier) {
            $results = $this->searchTier($query, $filters, $limit, $tier, $project);

            if ($this->hasConfidentMatches($results)) {
                return $results;
            }
        }

        // No confident matches at any tier - return all results merged and ranked
        return $this->searchAllTiers($query, $filters, $limit, $project);
    }

    /**
     * Search a specific tier and return results with tier labels.
     *
     * @param  array<string, string>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function searchTier(
        string $query,
        array $filters,
        int $limit,
        SearchTier $tier,
        string $project = 'default',
    ): Collection {
        $tierFilters = $this->buildTierFilters($filters, $tier);
        $results = $this->qdrantService->search($query, $tierFilters, $limit, $project);

        return $this->rankAndLabel($results, $tier);
    }

    /**
     * Calculate tiered search score: relevance * confidence_weight * freshness_decay.
     *
     * @param  array<string, mixed>  $entry
     */
    public function calculateScore(array $entry): float
    {
        $relevance = (float) ($entry['score'] ?? 0.0);
        $confidenceWeight = $this->calculateConfidenceWeight($entry);
        $freshnessDecay = $this->calculateFreshnessDecay($entry);

        return $relevance * $confidenceWeight * $freshnessDecay;
    }

    /**
     * Calculate confidence weight as a 0-1 multiplier.
     *
     * @param  array<string, mixed>  $entry
     */
    public function calculateConfidenceWeight(array $entry): float
    {
        $effectiveConfidence = $this->metadataService->calculateEffectiveConfidence($entry);

        return $effectiveConfidence / 100.0;
    }

    /**
     * Calculate freshness decay using exponential decay with half-life.
     *
     * @param  array<string, mixed>  $entry
     */
    public function calculateFreshnessDecay(array $entry): float
    {
        $updatedAt = $entry['updated_at'] ?? $entry['created_at'] ?? null;

        if (! is_string($updatedAt) || $updatedAt === '') {
            return 0.5;
        }

        $daysSince = Carbon::parse($updatedAt)->diffInDays(now());

        return pow(0.5, $daysSince / self::FRESHNESS_HALF_LIFE_DAYS);
    }

    /**
     * Check if results contain confident matches above threshold.
     *
     * @param  Collection<int, array<string, mixed>>  $results
     */
    private function hasConfidentMatches(Collection $results): bool
    {
        if ($results->isEmpty()) {
            return false;
        }

        return $results->contains(fn (array $entry): bool => ($entry['tiered_score'] ?? 0.0) >= self::CONFIDENCE_THRESHOLD);
    }

    /**
     * Search all tiers and merge/deduplicate results.
     *
     * @param  array<string, string>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function searchAllTiers(
        string $query,
        array $filters,
        int $limit,
        string $project,
    ): Collection {
        $allResults = collect();

        foreach (SearchTier::searchOrder() as $tier) {
            $results = $this->searchTier($query, $filters, $limit, $tier, $project);
            $allResults = $allResults->merge($results);
        }

        // Deduplicate by ID, keeping the highest scored version
        return $allResults
            ->groupBy('id')
            ->map(function (Collection $group): array {
                $first = $group->sortByDesc('tiered_score')->first();

                return is_array($first) ? $first : [];
            })
            ->filter(fn (array $entry): bool => count($entry) > 0)
            ->values()
            ->sortByDesc('tiered_score')
            ->take($limit)
            ->values();
    }

    /**
     * Build Qdrant filters for a specific tier.
     *
     * @param  array<string, string>  $filters
     * @return array<string, string>
     */
    private function buildTierFilters(array $filters, SearchTier $tier): array
    {
        return match ($tier) {
            SearchTier::Working => array_merge($filters, ['status' => 'draft']),
            SearchTier::Recent => $filters,
            SearchTier::Structured => array_merge($filters, ['status' => 'validated']),
            SearchTier::Archive => array_merge($filters, ['status' => 'deprecated']),
        };
    }

    /**
     * Apply ranking formula and add tier labels to results.
     *
     * @param  Collection<int, array<string, mixed>>  $results
     * @return Collection<int, array<string, mixed>>
     */
    private function rankAndLabel(Collection $results, SearchTier $tier): Collection
    {
        return $results
            ->filter(fn (array $entry): bool => $this->entryMatchesTierTimeConstraint($entry, $tier))
            ->map(function (array $entry) use ($tier): array {
                $entry['tier'] = $tier->value;
                $entry['tier_label'] = $tier->label();
                $entry['tiered_score'] = $this->calculateScore($entry);

                return $entry;
            })
            ->sortByDesc('tiered_score')
            ->values();
    }

    /**
     * Check if an entry matches the time constraint for a tier.
     *
     * @param  array<string, mixed>  $entry
     */
    private function entryMatchesTierTimeConstraint(array $entry, SearchTier $tier): bool
    {
        if ($tier !== SearchTier::Recent) {
            return true;
        }

        $updatedAt = $entry['updated_at'] ?? $entry['created_at'] ?? null;

        if (! is_string($updatedAt) || $updatedAt === '') {
            return false;
        }

        return Carbon::parse($updatedAt)->diffInDays(now()) <= self::RECENT_DAYS;
    }
}
