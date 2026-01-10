<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ObservationType;
use App\Models\Session;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class SessionService
{
    /**
     * Get all active sessions (where ended_at is null).
     *
     * @return EloquentCollection<int, Session>
     */
    public function getActiveSessions(): EloquentCollection
    {
        /** @var EloquentCollection<int, Session> */
        return Session::query()
            ->whereNull('ended_at')
            ->orderBy('started_at', 'desc')
            ->get();
    }

    /**
     * Get recent sessions with optional filters.
     *
     * @return EloquentCollection<int, Session>
     */
    public function getRecentSessions(int $limit = 20, ?string $project = null): EloquentCollection
    {
        $query = Session::query();

        if ($project !== null) {
            $query->where('project', $project);
        }

        /** @var EloquentCollection<int, Session> */
        return $query
            ->orderBy('started_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get a session with its observations loaded.
     * Supports both full UUIDs and partial IDs (minimum 8 characters).
     *
     * @return Session|null|array{error: string, matches: array<string>} Returns Session on success, null if not found, or error array if multiple matches
     */
    public function getSessionWithObservations(string $id): Session|array|null
    {
        // First try exact match
        $exactMatch = Session::query()
            ->with('observations')
            ->find($id);

        if ($exactMatch !== null) {
            return $exactMatch;
        }

        // If no exact match and ID is short enough to be partial, try partial match
        if (strlen($id) < 36) { // UUID length is 36 characters
            $matches = Session::query()
                ->where('id', 'like', $id.'%')
                ->with('observations')
                ->get();

            if ($matches->count() === 1) {
                return $matches->first();
            }

            if ($matches->count() > 1) {
                return [
                    'error' => 'Multiple sessions found with this prefix. Please use a more specific ID.',
                    'matches' => $matches->pluck('id')->toArray(),
                ];
            }
        }

        return null;
    }

    /**
     * Get observations for a session with optional type filter.
     *
     * @return EloquentCollection<int, \App\Models\Observation>
     */
    public function getSessionObservations(string $id, ?ObservationType $type = null): EloquentCollection
    {
        $session = Session::query()->find($id);

        if ($session === null) {
            /** @var EloquentCollection<int, \App\Models\Observation> */
            return new EloquentCollection;
        }

        $query = $session->observations();

        if ($type !== null) {
            $query->where('type', $type);
        }

        /** @var EloquentCollection<int, \App\Models\Observation> */
        return $query
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
