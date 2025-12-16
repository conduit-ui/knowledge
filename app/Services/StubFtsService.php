<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FullTextSearchInterface;
use Illuminate\Database\Eloquent\Collection;

class StubFtsService implements FullTextSearchInterface
{
    /**
     * Search observations using full-text search.
     * This is a stub implementation that returns an empty collection.
     *
     * @param  string  $query  The search query
     * @param  array<string, mixed>  $filters  Optional filters (type, session_id, concept)
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Observation>
     */
    public function searchObservations(string $query, array $filters = []): Collection
    {
        return new Collection;
    }

    /**
     * Check if full-text search is available.
     * This is a stub implementation that always returns false.
     */
    public function isAvailable(): bool
    {
        return false;
    }

    /**
     * Rebuild the FTS index.
     * This is a stub implementation that does nothing.
     */
    public function rebuildIndex(): void
    {
        // No-op
    }
}
