# Knowledge CLI Documentation

Generated from knowledge base on $(date)

## Overview

Knowledge CLI is an AI-powered knowledge base with semantic search, Qdrant vector storage, and Ollama intelligence. It captures technical decisions, learnings, and context from your work for easy retrieval via semantic search.

## Architecture

The system uses a tiered architecture:

```
CLI Interface (Laravel Zero)
    ↓
Tiered Search Service (4-level retrieval)
    ↓
Qdrant Service (Vector Database)
    ├── Per-project collections
    ├── Metadata-based queries
    └── Semantic similarity search
    ↓
Redis Cache (KnowledgeCacheService)
    ↓
Embedding Service (sentence-transformers)
    ↓
Ollama Service (optional AI enhancement)
    ↓
Remote Sync Service (optional)
```

### Core Services

| Service | Purpose |
|---------|---------|
| `QdrantService` | All vector DB operations (upsert, search, delete, collections) |
| `EmbeddingService` | Text-to-vector conversion using sentence-transformers |
| `KnowledgeCacheService` | Redis caching for sub-200ms queries |
| `TieredSearchService` | Narrow-to-wide retrieval across 4 search tiers |
| `OllamaService` | LLM integration for auto-tagging and query expansion |
| `RemoteSyncService` | Background sync to centralized remote server |
| `WriteGateService` | Filters knowledge quality before persistence |
| `EntryMetadataService` | Staleness detection, confidence degradation |
| `CorrectionService` | Multi-tier correction propagation |
| `DailyLogService` | Entry staging before permanent storage |
| `GitContextService` | Auto-detect git repo, branch, commit, author |
| `ProjectDetectorService` | Auto-detect project namespace from git repo |
| `EnhancementQueueService` | File-based queue for async Ollama auto-tagging |
| `PatternDetectorService` | Detect duplicate/similar entries before persistence |

## Command Categories

### Core Knowledge Management

#### Adding Knowledge
```bash
# Basic entry with auto-detected git context
./know add "Fix Auth Timeout" --content="Increase token TTL in config/auth.php" --tags=auth,debugging

# Add with specific category and priority
./know add "Database Migration Fix" --content="Rollback failed migration, then re-run with --step" --category=debugging --priority=high

# Skip git context detection
./know add "Personal Note" --content="Remember to update documentation" --no-git
```

#### Searching Knowledge
```bash
# Semantic search
./know search "authentication timeout issues"

# Search with filters
./know search --category=debugging --tag=auth --limit=5

# Search across all projects
./know search "database patterns" --global

# Code search (if indexed)
./know search-code "user authentication"
```

#### Managing Entries
```bash
# Show entry details
./know show <uuid>

# Update existing entry
./know update <uuid> --content="Updated content" --tags=new,updated

# Validate entry (boosts confidence)
./know validate <uuid>

# Archive entry (soft delete)
./know archive <uuid>

# Export entries
./know export <uuid> --format=json
./know export:all --output=backup.json
```

### Intelligence Features

#### Context and Insights
```bash
# Load semantic context for AI tools
./know context --project=myapp --limit=10

# Generate AI insights
./know insights --topic=architecture

# Create daily synthesis
./know synthesize --date=2025-01-15
```

#### Staging and Enhancement
```bash
# Stage entries temporarily
./know stage "Temporary note" --content="Will promote later"

# Promote staged entries
./know promote --all

# Process enhancement queue
./know enhance:worker --limit=50
```

### Infrastructure Management

#### Installation and Setup
```bash
# Initialize Qdrant collection
./know install

# Install with specific project namespace
./know install --project=myapp

# Install globally
./know install --global
```

#### Configuration
```bash
# Show current config
./know config

# Set configuration values
./know config set qdrant.host=localhost
./know config set search.limit=20

# List projects
./know projects
```

#### Service Management
```bash
# Start all services
./know service:up

# Check service status
./know service:status

# View service logs
./know service:logs --service=qdrant

# Stop services
./know service:down
```

### Health and Maintenance

#### Status Checks
```bash
# Overall system status
./know stats

# Search infrastructure health
./know search:status

# Agent dependency health
./know agent:status
```

#### Maintenance
```bash
# Run maintenance tasks
./know maintain

# Clean up old entries
./know maintain --cleanup-days=90

# Optimize collections
./know maintain --optimize
```

### Synchronization

#### Remote Sync
```bash
# Bidirectional sync
./know sync --push --pull

# Background sync
./know sync:remote --daemon

# Purge sync queue
./know sync:purge
```

### Code Intelligence

#### Code Indexing
```bash
# Index current codebase
./know index-code ./src

# Semantic code search
./know search-code "authentication middleware"

# Show git context
./know git:context
```

## Configuration

### Environment Variables

```env
# Qdrant Configuration
QDRANT_HOST=localhost
QDRANT_PORT=6333
QDRANT_COLLECTION=knowledge_default
QDRANT_SECURE=false

# Embedding Server
EMBEDDING_SERVER_URL=http://localhost:8001
EMBEDDING_MODEL=all-MiniLM-L6-v2

# Redis Cache
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0

# Ollama (Optional)
OLLAMA_HOST=http://localhost:11434
OLLAMA_MODEL=llama2

# Search Configuration
SEARCH_EMBEDDING_DIMENSION=384
SEARCH_MINIMUM_SIMILARITY=0.7
SEARCH_LIMIT=20

# Write Gate Configuration
WRITE_GATE_ENABLED=true
```

### User Configuration

User-specific configuration can be placed in `~/.knowledge/config.json`:

```json
{
  "qdrant": {
    "url": "http://qdrant-server:6333",
    "collection": "my-knowledge"
  },
  "embeddings": {
    "url": "http://custom-embeddings:9001"
  },
  "write_gate": {
    "criteria": {
      "behavioral_impact": true,
      "commitment_weight": true,
      "decision_rationale": true,
      "durable_facts": false,
      "explicit_instruction": true
    }
  }
}
```

## Write Gate Criteria

The Write Gate service ensures knowledge quality before persistence. Entries must demonstrate at least one of:

- **Behavioral Impact**: Changes how work is done
- **Commitment Weight**: Represents significant decisions or commitments
- **Decision Rationale**: Explains why decisions were made
- **Durable Facts**: Long-lasting technical truths
- **Explicit Instruction**: Clear guidance for future actions

Use `--force` to bypass the write gate when necessary.

## Tiered Search Strategy

The TieredSearchService implements a 4-level search strategy:

1. **Exact Matches**: Direct title/content matches
2. **Semantic Similarity**: Vector similarity search
3. **Related Concepts**: Tag and metadata relationships
4. **Global Fallback**: Broad search across all collections

This ensures relevant results are found quickly while providing comprehensive coverage.

## Project Namespacing

Knowledge is automatically namespaced by project:

- Auto-detection from git repository
- Manual override with `--project=<name>`
- Global search with `--global` flag
- Per-project collections in Qdrant

## Development Guide

### Quality Standards

- **95% test coverage** enforced by Sentinel Gate CI
- **PHPStan level 8** with strict rules
- **Laravel Pint** code style (Laravel preset)
- **Auto-merge** after Sentinel Gate certification

### Running Tests

```bash
composer test              # Run all tests (Pest, parallel)
composer test-coverage     # Run with coverage report
composer format            # Format code (Laravel Pint)
composer analyse           # Static analysis (PHPStan level 8)
```

### Code Structure

```
app/
├── Commands/          # CLI commands
├── Services/          # Business logic services
├── Contracts/         # Service interfaces
├── Integrations/      # External API clients (Saloon)
└── Providers/        # Laravel service providers

tests/
├── Feature/           # Feature tests
└── Unit/              # Unit tests

docker/
└── embedding-server/  # Python FastAPI embedding service
```

## Docker Services

### Starting Services

```bash
# Using Makefile
make up

# Using docker-compose
docker compose up -d
```

Services started:
- **Embedding Server**: `http://localhost:8001` (sentence-transformers)
- **Qdrant**: `http://localhost:6333` (vector database)

### Remote Server Setup

For production environments, use `docker-compose.remote.yml` to bind services to specific network interfaces (Tailscale, VPN, LAN).

## Troubleshooting

### Common Issues

1. **Connection Errors**: Ensure Qdrant and Redis are running
2. **Embedding Failures**: Check embedding server is accessible
3. **Slow Searches**: Verify Redis cache is working
4. **Write Gate Rejections**: Add decision rationale or behavioral impact

### Health Checks

```bash
# Check all services
./know agent:status

# Check search specifically
./know search:status

# View detailed stats
./know stats --verbose
```

## API Reference

### Service Interfaces

All services implement contracts for testability:

- `EmbeddingServiceInterface`: Vector generation
- `QdrantServiceInterface`: Vector database operations
- `HealthCheckInterface`: Service health monitoring

### HTTP Integrations

Uses Saloon for HTTP clients:

- `QdrantConnector`: Qdrant API client
- `EmbeddingConnector`: Embedding server client
- `OllamaConnector`: Ollama API client

## License

MIT License. See [LICENSE](LICENSE) for details.