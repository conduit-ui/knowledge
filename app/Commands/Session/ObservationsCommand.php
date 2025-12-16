<?php

declare(strict_types=1);

namespace App\Commands\Session;

use App\Enums\ObservationType;
use App\Services\SessionService;
use LaravelZero\Framework\Commands\Command;

class ObservationsCommand extends Command
{
    protected $signature = 'session:observations
                            {id : The session ID}
                            {--type= : Filter by observation type}';

    protected $description = 'List observations for a session';

    public function handle(SessionService $service): int
    {
        $id = $this->argument('id');
        $typeOption = $this->option('type');

        if (! is_string($id)) {
            $this->error('The ID must be a valid string.');

            return self::FAILURE;
        }

        $type = null;
        if (is_string($typeOption)) {
            $type = ObservationType::tryFrom($typeOption);

            if ($type === null) {
                $this->error("Invalid observation type: {$typeOption}");
                $this->line('Valid types: '.implode(', ', array_column(ObservationType::cases(), 'value')));

                return self::FAILURE;
            }
        }

        $observations = $service->getSessionObservations($id, $type);

        if ($observations->isEmpty()) {
            $this->info('No observations found.');

            return self::SUCCESS;
        }

        $tableData = $observations->map(function ($observation): array {
            /** @var \App\Models\Observation $observation */
            return [
                'id' => (string) $observation->id,
                'type' => $observation->type->value,
                'title' => $observation->title,
                'created' => $observation->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        $this->table(
            ['ID', 'Type', 'Title', 'Created At'],
            $tableData
        );

        return self::SUCCESS;
    }
}
