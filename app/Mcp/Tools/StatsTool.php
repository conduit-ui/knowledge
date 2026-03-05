<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\ProjectDetectorService;
use App\Services\QdrantService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get knowledge base statistics: entry counts, project namespaces, and system health.')]
#[IsReadOnly]
#[IsIdempotent]
class StatsTool extends Tool
{
    public function __construct(
        private readonly QdrantService $qdrant,
        private readonly ProjectDetectorService $projectDetector,
    ) {}

    public function handle(Request $request): Response
    {
        $project = is_string($request->get('project')) ? $request->get('project') : $this->projectDetector->detect();

        $collections = $this->qdrant->listCollections();
        $projects = [];

        foreach ($collections as $collection) {
            $projectName = str_replace('knowledge_', '', $collection);
            $count = $this->qdrant->count($projectName);
            $projects[$projectName] = $count;
        }

        $currentProjectCount = $projects[$project] ?? 0;
        $totalEntries = array_sum($projects);

        return Response::text(json_encode([
            'current_project' => $project,
            'current_project_entries' => $currentProjectCount,
            'total_entries' => $totalEntries,
            'projects' => $projects,
            'project_count' => count($projects),
        ], JSON_THROW_ON_ERROR));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()
                ->description('Project to focus on. Auto-detected from git if omitted.'),
        ];
    }
}
