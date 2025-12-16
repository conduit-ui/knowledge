<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface FullTextSearchInterface
{
    /**
     * Search observations using full-text search.
     *
     * @param  string  $query  The search query
     * @param  array<string, mixed>  $filters  Optional filters (type, session_id, concept)
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Observation>
     */
    public function searchObservations(string $query, array $filters = []): Collection;

    /**
     * Check if full-text search is available.
     */
    public function isAvailable(): bool;

    /**
     * Rebuild the FTS index.
     */
    public function rebuildIndex(): void;
}
