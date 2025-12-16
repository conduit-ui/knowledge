<?php

declare(strict_types=1);

namespace App\Commands\Observe;

use App\Enums\ObservationType;
use App\Models\Session;
use App\Services\ObservationService;
use Illuminate\Support\Carbon;
use LaravelZero\Framework\Commands\Command;

class AddCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'observe:add
                            {title : The title of the observation}
                            {--type=discovery : Type of observation (bugfix, feature, refactor, discovery, decision, change)}
                            {--concept= : Concept or topic this observation relates to}
                            {--session= : Session UUID (creates ephemeral session if not provided)}
                            {--narrative= : Detailed narrative of what happened}
                            {--facts=* : Facts in key=value format (repeatable)}
                            {--files-read=* : Files that were read (repeatable)}
                            {--files-modified=* : Files that were modified (repeatable)}';

    /**
     * @var string
     */
    protected $description = 'Add a new observation to a session';

    public function handle(ObservationService $observationService): int
    {
        $title = $this->argument('title');
        $type = $this->option('type');
        $concept = $this->option('concept');
        $sessionId = $this->option('session');
        $narrative = $this->option('narrative');
        $facts = $this->option('facts');
        $filesRead = $this->option('files-read');
        $filesModified = $this->option('files-modified');

        // Validate required fields
        if ($narrative === null || $narrative === '') {
            $this->error('The narrative field is required.');

            return self::FAILURE;
        }

        // Validate type
        $observationType = $this->validateType($type);
        if ($observationType === null) {
            return self::FAILURE;
        }

        // Get or create session
        $session = $this->getOrCreateSession($sessionId);
        if ($session === null) {
            return self::FAILURE;
        }

        // Parse facts from key=value format
        $parsedFacts = $this->parseFacts($facts);

        // Create observation data
        $data = [
            'session_id' => $session->id,
            'type' => $observationType,
            'title' => $title,
            'narrative' => $narrative,
        ];

        if (is_string($concept) && $concept !== '') {
            $data['concept'] = $concept;
        }

        if (count($parsedFacts) > 0) {
            $data['facts'] = $parsedFacts;
        }

        if (is_array($filesRead) && count($filesRead) > 0) {
            $data['files_read'] = $filesRead;
        }

        if (is_array($filesModified) && count($filesModified) > 0) {
            $data['files_modified'] = $filesModified;
        }

        $observation = $observationService->createObservation($data);

        $this->info("Observation created successfully with ID: {$observation->id}");
        $this->line("Title: {$observation->title}");
        $this->line("Type: {$observation->type->value}");

        if ($observation->concept !== null) {
            $this->line("Concept: {$observation->concept}");
        }

        $this->line("Session: {$observation->session_id}");

        return self::SUCCESS;
    }

    /**
     * Validate and return the ObservationType.
     */
    private function validateType(mixed $type): ?ObservationType
    {
        // @codeCoverageIgnoreStart
        if (! is_string($type)) {
            $this->error('The type must be a string.');

            return null;
        }
        // @codeCoverageIgnoreEnd

        $observationType = ObservationType::tryFrom($type);

        if ($observationType === null) {
            $validTypes = implode(', ', array_column(ObservationType::cases(), 'value'));
            $this->error("The selected type is invalid. Valid options: {$validTypes}");

            return null;
        }

        return $observationType;
    }

    /**
     * Get existing session or create ephemeral session.
     */
    private function getOrCreateSession(mixed $sessionId): ?Session
    {
        if ($sessionId !== null) {
            // @codeCoverageIgnoreStart
            if (! is_string($sessionId)) {
                $this->error('The session must be a valid UUID.');

                return null;
            }
            // @codeCoverageIgnoreEnd

            /** @var Session|null $session */
            $session = Session::query()->find($sessionId);

            if ($session === null) {
                $this->error("Session with ID {$sessionId} not found.");

                return null;
            }

            return $session;
        }

        // Create ephemeral session
        return Session::query()->create([
            'project' => 'ephemeral',
            'started_at' => Carbon::now(),
        ]);
    }

    /**
     * Parse facts from key=value format.
     *
     * @return array<string, string>
     */
    private function parseFacts(mixed $facts): array
    {
        // @codeCoverageIgnoreStart
        if (! is_array($facts)) {
            return [];
        }
        // @codeCoverageIgnoreEnd

        $parsed = [];

        foreach ($facts as $fact) {
            // @codeCoverageIgnoreStart
            if (! is_string($fact)) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            if (! str_contains($fact, '=')) {
                continue;
            }

            $parts = explode('=', $fact, 2);
            $key = trim($parts[0]);
            $value = trim($parts[1]);

            if ($key !== '' && $value !== '') {
                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }
}
