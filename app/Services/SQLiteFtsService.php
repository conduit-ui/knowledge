<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FullTextSearchInterface;
use App\Models\Observation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SQLiteFtsService implements FullTextSearchInterface
{
    /**
     * Search observations using full-text search.
     *
     * @param  string  $query  The search query
     * @param  array<string, mixed>  $filters  Optional filters (type, session_id, concept)
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Observation>
     */
    public function searchObservations(string $query, array $filters = []): Collection
    {
        if ($query === '') {
            return new Collection;
        }

        if (! $this->isAvailable()) {
            return new Collection;
        }

        // Build the FTS query - searches title, subtitle, narrative, concept
        // Quote the query to handle special characters like hyphens
        $quotedQuery = '"'.str_replace('"', '""', $query).'"';

        $ftsQuery = DB::table('observations_fts')
            ->select('observations_fts.rowid', DB::raw('rank as fts_rank'))
            ->whereRaw('observations_fts MATCH ?', [$quotedQuery])
            ->orderBy('fts_rank');

        // Get matching observation IDs with their ranks
        $results = $ftsQuery->get();

        if ($results->isEmpty()) {
            return new Collection;
        }

        // Fetch full Observation models
        $observationIds = $results->pluck('rowid')->toArray();
        $observationsQuery = Observation::query()
            ->whereIn('id', $observationIds);

        // Apply filters on the actual observations table
        if (isset($filters['type'])) {
            $observationsQuery->where('type', $filters['type']);
        }

        if (isset($filters['session_id'])) {
            $observationsQuery->where('session_id', $filters['session_id']);
        }

        if (isset($filters['concept'])) {
            $observationsQuery->where('concept', $filters['concept']);
        }

        $observations = $observationsQuery->get();
        /** @var \Illuminate\Database\Eloquent\Collection<int, Observation> $observations */

        // Sort observations by FTS rank order
        $rankedIds = $results->pluck('rowid')->flip();

        return $observations->sortBy(function ($observation) use ($rankedIds) {
            return $rankedIds[$observation->id] ?? 9999;
        })->values();
    }

    /**
     * Check if full-text search is available.
     */
    public function isAvailable(): bool
    {
        try {
            // Check if the observations_fts table exists
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name='observations_fts'");

            return count($tables) > 0;
            // @codeCoverageIgnoreStart
        } catch (\Exception $e) {
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Rebuild the FTS index.
     */
    public function rebuildIndex(): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        try {
            // FTS5 rebuild command
            DB::statement("INSERT INTO observations_fts(observations_fts) VALUES('rebuild')");
            // @codeCoverageIgnoreStart
        } catch (\Exception $e) {
            // Silent fail - index rebuild is optional
        }
        // @codeCoverageIgnoreEnd
    }
}
