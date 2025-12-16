<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FullTextSearchInterface;
use App\Enums\ObservationType;
use App\Models\Observation;
use Illuminate\Database\Eloquent\Collection;

class ObservationService
{
    public function __construct(
        private FullTextSearchInterface $ftsService
    ) {}

    /**
     * Create a new observation.
     *
     * @param  array<string, mixed>  $data
     */
    public function createObservation(array $data): Observation
    {
        // Ensure default values for token fields
        $data['work_tokens'] = $data['work_tokens'] ?? 0;
        $data['read_tokens'] = $data['read_tokens'] ?? 0;

        return Observation::query()->create($data);
    }

    /**
     * Search observations using full-text search.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Observation>
     */
    public function searchObservations(string $query, array $filters = []): Collection
    {
        return $this->ftsService->searchObservations($query, $filters);
    }

    /**
     * Get observations by type.
     *
     * @return Collection<int, Observation>
     */
    public function getObservationsByType(ObservationType $type, int $limit = 10): Collection
    {
        $query = Observation::query()
            ->where('type', $type)
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        /** @var Collection<int, Observation> */
        return $query->get();
    }

    /**
     * Get recent observations.
     *
     * @return Collection<int, Observation>
     */
    public function getRecentObservations(int $limit = 10): Collection
    {
        $query = Observation::query()
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        /** @var Collection<int, Observation> */
        return $query->get();
    }
}
