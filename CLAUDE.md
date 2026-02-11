# CLAUDE.md

This file provides guidance to Claude Code when working with this repository.

## What This Is

Knowledge CLI — an AI-powered knowledge base with semantic search, Qdrant vector storage, and Ollama intelligence. Built as a Laravel Zero CLI application.

**Entry point**: `./know` (not `artisan`)

## Commands

```bash
composer test              # Run all tests (Pest, parallel)
composer test-coverage     # Run with coverage report
composer format            # Format code (Laravel Pint)
composer analyse           # Static analysis (PHPStan level 8)
./know list                # List all available commands
```

Run a single test:
```bash
vendor/bin/pest tests/Feature/Commands/KnowledgeSearchCommandTest.php
```

## Architecture

- **Storage**: Qdrant vector database only (no SQLite, no Eloquent models)
- **Cache**: Redis via KnowledgeCacheService
- **Embeddings**: sentence-transformers via EmbeddingService
- **LLM**: Ollama via OllamaService (optional, for auto-tagging)
- **HTTP**: Saloon connectors in `app/Integrations/Qdrant/`
- **Commands**: `app/Commands/` — extend `LaravelZero\Framework\Commands\Command`
- **Services**: `app/Services/` — registered in `app/Providers/AppServiceProvider.php`
- **Tests**: `tests/Feature/` and `tests/Unit/` — Pest framework

## Quality Standards (Non-Negotiable)

- **95% test coverage** enforced by Sentinel Gate CI
- **PHPStan level 8** with strict rules
- **Laravel Pint** code style (Laravel preset)
- **Auto-merge** after Sentinel Gate certification passes

## Key Services

| Service | Purpose |
|---------|---------|
| `QdrantService` | All vector DB operations (upsert, search, delete, collections) |
| `EmbeddingService` | Text-to-vector conversion |
| `KnowledgeCacheService` | Redis caching for sub-200ms queries |
| `RemoteSyncService` | Background sync to centralized remote server |
| `WriteGateService` | Filters knowledge quality before persistence |
| `EntryMetadataService` | Staleness detection, confidence degradation |
| `CorrectionService` | Multi-tier correction propagation |
| `DailyLogService` | Entry staging before permanent storage |
| `GitContextService` | Auto-detect git repo, branch, commit, author |
| `TieredSearchService` | Narrow-to-wide retrieval across 4 search tiers |
| `ProjectDetectorService` | Auto-detect project namespace from git repo |
| `EnhancementQueueService` | File-based queue for async Ollama auto-tagging |
| `OllamaService` | LLM integration for auto-tagging and query expansion |
| `PatternDetectorService` | Detect duplicate/similar entries before persistence |

## TDD Workflow

1. Write failing test (RED)
2. Make test pass (GREEN)
3. Refactor if needed
4. Push PR — auto-merges after Sentinel Gate passes
