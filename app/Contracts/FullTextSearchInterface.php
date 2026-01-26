<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Support\Collection;

/**
 * Interface for full-text search operations.
 *
 * This interface defines the contract for searching observations
 * and other entities using full-text search capabilities.
 */
interface FullTextSearchInterface
{
    /**
     * Search observations by query string.
     *
     * @param  string  $query  The search query
     * @return Collection<int, mixed> Collection of matching observations
     */
    public function searchObservations(string $query): Collection;
}
