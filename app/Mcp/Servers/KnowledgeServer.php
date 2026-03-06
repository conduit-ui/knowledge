<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\ContextTool;
use App\Mcp\Tools\CorrectTool;
use App\Mcp\Tools\RecallTool;
use App\Mcp\Tools\RememberTool;
use App\Mcp\Tools\SearchCodeTool;
use App\Mcp\Tools\StatsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Knowledge')]
#[Version('1.0.0')]
#[Instructions('Semantic knowledge base with vector search. Use `recall` to search, `remember` to capture discoveries, `correct` to fix wrong knowledge, `context` to load project-relevant entries, and `stats` for health checks. All tools auto-detect the current project from git context.')]
class KnowledgeServer extends Server
{
    protected array $tools = [
        RecallTool::class,
        RememberTool::class,
        CorrectTool::class,
        ContextTool::class,
        StatsTool::class,
        SearchCodeTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
