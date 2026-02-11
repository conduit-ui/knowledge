# Knowledge CLI

AI-powered knowledge base with semantic search, Qdrant vector storage, and Ollama intelligence.

## What It Does

Captures technical decisions, learnings, and context from your work. Retrieves exactly what you need via semantic search — especially for AI pair programming with Claude Code.

```bash
# Add knowledge
./know add "Database Connection Fix" --content="Check .env before debugging migrations" --tags=debugging,database

# Semantic search
./know search "how to fix database issues"

# Show entry details
./know show <uuid>
```

## Architecture

```
CLI (Laravel Zero)
    ↓
Tiered Search (narrow-to-wide retrieval across 4 tiers)
    ↓
Qdrant (Vector DB - all storage)
    ├── Per-project collections (auto-detected from git)
    └── Payload-based metadata (JSON)
    ↓
Redis (Cache layer - sub-200ms queries)
    ↓
Embedding Server (sentence-transformers)
    ↓
Ollama (optional - async auto-tagging via background queue)
    ↓
Remote Sync (optional - background sync to centralized server)
```

No SQLite. No schema migrations. Pure vector storage. Per-project isolation via auto-detected git namespaces.

## Commands

All commands support `--project=<name>` to target a specific project namespace and `--global` to search across all projects. Project is auto-detected from the current git repository.

### Core Knowledge

| Command | Description |
|---------|-------------|
| `add` | Add a knowledge entry (auto-detects git context, async Ollama tagging) |
| `search` | Semantic vector search with tiered narrow-to-wide retrieval |
| `show <id>` | Display entry details |
| `entries` | List entries with filters |
| `update <id>` | Update an existing entry |
| `validate <id>` | Mark entry as validated (boosts confidence) |
| `archive <id>` | Soft-delete an entry |
| `export <id>` | Export a single entry |
| `export:all` | Bulk export all entries |
| `correct` | Correct/update knowledge with multi-tier propagation |

### Intelligence

| Command | Description |
|---------|-------------|
| `context` | Load semantic session context for AI tools |
| `insights` | AI-generated insights from your knowledge base |
| `synthesize` | Generate daily synthesis of knowledge themes |
| `stage` | Stage entries in daily log before permanent storage |
| `promote` | Promote staged entries to permanent knowledge |
| `enhance:worker` | Process the background Ollama enhancement queue |
| `coderabbit:extract` | Extract CodeRabbit review findings from a GitHub PR |

### Infrastructure

| Command | Description |
|---------|-------------|
| `install` | Initialize Qdrant collection |
| `config` | Manage configuration |
| `stats` | Analytics dashboard |
| `search:status` | Search infrastructure health check |
| `agent:status` | Dependency health checks (Qdrant, Redis, Ollama, Embeddings) |
| `maintain` | Run maintenance tasks |
| `projects` | List all project knowledge bases |

### Services (Docker)

| Command | Description |
|---------|-------------|
| `service:up` | Start Qdrant, Redis, embedding server |
| `service:down` | Stop services |
| `service:status` | Health check all services |
| `service:logs` | View service logs |

### Sync

| Command | Description |
|---------|-------------|
| `sync` | Bidirectional sync (--push / --pull) |
| `sync:remote` | Background sync to centralized remote server |
| `sync:purge` | Purge sync queue |

### Code Intelligence

| Command | Description |
|---------|-------------|
| `index-code` | Index codebase for semantic code search |
| `search-code` | Semantic search across indexed code |
| `git:context` | Display current git context |

## Quick Start

### 1. Start Services

```bash
# Docker compose (Qdrant + embedding server)
make up

# Or manually
docker compose up -d
```

This starts:
- **Qdrant** on `http://localhost:6333` — Vector database
- **Embedding Server** on `http://localhost:8001` — sentence-transformers (all-MiniLM-L6-v2)

### 2. Initialize

```bash
./know install
```

### 3. Add Knowledge

```bash
# With automatic git context detection
./know add "Fix Auth Timeout" --content="Increase token TTL in config/auth.php" --tags=auth,debugging

# Skip git detection
./know add "API Keys" --content="Store in vault, never in .env" --no-git
```

### 4. Search

```bash
# Semantic search
./know search "authentication timeout issues"

# With filters
./know search --category=debugging --tag=auth --limit=5
```

## Configuration

`.env` file:

```env
QDRANT_HOST=localhost
QDRANT_PORT=6333
EMBEDDING_SERVER_URL=http://localhost:8001
REDIS_HOST=localhost
REDIS_PORT=6379
OLLAMA_HOST=http://localhost:11434
```

### Remote Server (Production)

Uses `docker-compose.remote.yml` to bind services to a specific network interface (e.g. Tailscale, VPN, LAN) for centralized knowledge sync across multiple machines.

## Development

```bash
composer install          # Install dependencies
composer test             # Run tests (Pest, parallel)
composer test-coverage    # Run with coverage report
composer format           # Format code (Laravel Pint)
composer analyse          # Static analysis (PHPStan level 8)
```

## Quality Standards

- **Test Coverage**: 95% minimum (enforced by Sentinel Gate CI)
- **Static Analysis**: PHPStan Level 8 with strict rules
- **Code Style**: Laravel Pint (Laravel preset)
- **CI/CD**: Sentinel Gate auto-merges PRs after certification

## Stack

- **Runtime**: PHP 8.2+, Laravel Zero
- **Vector DB**: Qdrant (Rust)
- **Cache**: Redis
- **Embeddings**: sentence-transformers (Python/FastAPI)
- **LLM**: Ollama (optional, for auto-tagging and query expansion)
- **HTTP Client**: Saloon
- **Testing**: Pest
- **CI**: GitHub Actions (Sentinel Gate)

## License

MIT License. See [LICENSE](LICENSE) for details.
