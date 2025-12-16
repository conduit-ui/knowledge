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
     */
    public function getSessionWithObservations(string $id): ?Session
    {
        /** @var Session|null */
        return Session::query()
            ->with('observations')
            ->find($id);
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
