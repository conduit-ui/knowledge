<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\CorrectionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Correct wrong knowledge. Supersedes the original entry, creates a corrected version, and propagates corrections to related conflicting entries.')]
class CorrectTool extends Tool
{
    public function __construct(
        private readonly CorrectionService $correctionService,
    ) {}

    public function handle(Request $request): Response
    {
        $id = $request->get('id');
        $correctedContent = $request->get('corrected_content');

        if (! is_string($id) || $id === '') {
            return Response::error('Provide the ID of the entry to correct.');
        }

        if (! is_string($correctedContent) || strlen($correctedContent) < 10) {
            return Response::error('Provide corrected content of at least 10 characters.');
        }

        try {
            $result = $this->correctionService->correct($id, $correctedContent);

            return Response::text(json_encode([
                'status' => 'corrected',
                'corrected_entry_id' => $result['corrected_entry_id'],
                'original_id' => $id,
                'superseded_ids' => $result['superseded_ids'],
                'conflicts_resolved' => $result['conflicts_found'],
                'message' => "Entry corrected. New entry: {$result['corrected_entry_id']}. "
                    .count($result['superseded_ids']).' related entries superseded.',
            ], JSON_THROW_ON_ERROR));
        } catch (\RuntimeException $e) {
            return Response::error('Correction failed: '.$e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('ID of the entry to correct (from a previous recall result).')
                ->required(),
            'corrected_content' => $schema->string()
                ->description('The corrected information that replaces the wrong content.')
                ->required(),
        ];
    }
}
