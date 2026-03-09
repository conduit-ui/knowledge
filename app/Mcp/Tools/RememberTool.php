<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Exceptions\Qdrant\DuplicateEntryException;
use App\Services\EnhancementQueueService;
use App\Services\GitContextService;
use App\Services\ProjectDetectorService;
use App\Services\QdrantService;
use App\Services\WriteGateService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Capture a discovery or insight into the knowledge base. Auto-detects git context, validates quality via write gate, and checks for duplicates.')]
class RememberTool extends Tool
{
    public function __construct(
        private readonly QdrantService $qdrant,
        private readonly WriteGateService $writeGate,
        private readonly GitContextService $gitContext,
        private readonly ProjectDetectorService $projectDetector,
        private readonly EnhancementQueueService $enhancementQueue,
    ) {}

    public function handle(Request $request): Response
    {
        $title = $request->get('title');
        $content = $request->get('content');

        if (! is_string($title) || strlen($title) < 5) {
            return Response::error('A title of at least 5 characters is required.');
        }

        if (! is_string($content) || strlen($content) < 10) {
            return Response::error('Content of at least 10 characters is required.');
        }

        $project = is_string($request->get('project')) ? $request->get('project') : $this->projectDetector->detect();

        /** @var array<string> $tags */
        $tags = is_array($request->get('tags')) ? $request->get('tags') : [];

        /** @var string|null $category */
        $category = is_string($request->get('category')) ? $request->get('category') : null;

        $entry = [
            'id' => Str::uuid()->toString(),
            'title' => $title,
            'content' => $content,
            'priority' => is_string($request->get('priority')) ? $request->get('priority') : 'medium',
            'confidence' => is_int($request->get('confidence')) ? $request->get('confidence') : 50,
            'status' => 'draft',
            'evidence' => is_string($request->get('evidence')) ? $request->get('evidence') : null,
            'last_verified' => now()->toIso8601String(),
        ];

        // Only add optional fields when they have values
        if ($category !== null) {
            $entry['category'] = $category;
        }
        if ($tags !== []) {
            $entry['tags'] = $tags;
        }

        // Auto-populate git context
        if ($this->gitContext->isGitRepository()) {
            $context = $this->gitContext->getContext();
            $entry['repo'] = $context['repo'] ?? null;
            $entry['branch'] = $context['branch'] ?? null;
            $entry['commit'] = $context['commit'] ?? null;
            $entry['author'] = $context['author'] ?? null;
        }

        // Write gate validation
        $gateResult = $this->writeGate->evaluate($entry);
        if (! $gateResult['passed']) {
            return Response::error('Write gate rejected: '.$gateResult['reason'].'. Improve the entry quality and try again.');
        }

        // Store with duplicate detection
        try {
            $this->qdrant->upsert($entry, $project, true);
        } catch (DuplicateEntryException $e) {
            return Response::text(json_encode([
                'status' => 'duplicate_detected',
                'existing_id' => $e->existingId,
                'similarity' => $e->similarityScore !== null ? round($e->similarityScore * 100, 1) : null,
                'message' => "Similar entry already exists (ID: {$e->existingId}). Use the `correct` tool to update it, or add more distinct content.",
            ], JSON_THROW_ON_ERROR));
        }

        // Queue for Ollama auto-tagging
        if ((bool) config('search.ollama.enabled', true)) {
            $this->enhancementQueue->queue($entry);
        }

        return Response::text(json_encode([
            'status' => 'created',
            'id' => $entry['id'],
            'title' => $entry['title'],
            'project' => $project,
            'confidence' => $entry['confidence'],
            'message' => 'Knowledge entry captured successfully.',
        ], JSON_THROW_ON_ERROR));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('Short summary of the knowledge (5-200 chars). e.g., "Pest arch() tests prevent namespace violations"')
                ->required(),
            'content' => $schema->string()
                ->description('Detailed description of the discovery or insight (10-10000 chars).')
                ->required(),
            'category' => $schema->string()
                ->enum(['architecture', 'patterns', 'decisions', 'gotchas', 'debugging', 'testing', 'deployment', 'security'])
                ->description('Knowledge category. Omit to let auto-tagging classify it.'),
            'tags' => $schema->array()
                ->description('Tags for categorization (max 10). e.g., ["laravel", "pest", "testing"]'),
            'priority' => $schema->string()
                ->enum(['critical', 'high', 'medium', 'low'])
                ->description('Priority level.')
                ->default('medium'),
            'confidence' => $schema->integer()
                ->description('How confident you are in this knowledge (0-100). Default 50.')
                ->default(50),
            'evidence' => $schema->string()
                ->description('Supporting evidence or reference URL.'),
            'project' => $schema->string()
                ->description('Project namespace. Auto-detected from git if omitted.'),
        ];
    }
}
