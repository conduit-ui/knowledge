# Knowledge

[![Sentinel Gate](https://github.com/conduit-ui/knowledge/actions/workflows/gate.yml/badge.svg)](https://github.com/conduit-ui/knowledge/actions/workflows/gate.yml)

A semantic knowledge base that lives in your terminal — and talks directly to your AI tools.

Knowledge captures the technical decisions, gotchas, and context you accumulate while working, then hands the right pieces back to you (or to Claude Code) via semantic vector search. It's a [Laravel Zero](https://laravel-zero.com) CLI backed entirely by [Qdrant](https://qdrant.tech) — no relational database, no schema migrations, just vectors and JSON payloads. Every entry is namespaced to a project automatically, detected from your git repository.

There are two ways in: the `know` command-line tool, and a local **MCP server** that exposes the same knowledge base as tools your AI agent can call directly.

```bash
# Capture something worth remembering
./know add "Database Connection Fix" \
  --content="Check .env before debugging migrations" \
  --tags=debugging,database

# Find it again, by meaning rather than keyword
./know search "how do I fix database issues"

# Load project context at the start of an AI session
./know context
```

## Requirements

- **PHP 8.2+**
- **Composer**
- **Docker** — for the Qdrant vector database (`make up` starts it for you)
- **Redis** *(optional)* — query/embedding cache for sub-200ms reads
- **Ollama** *(optional)* — background auto-tagging and categorization
- An **embedding server** reachable at `QDRANT_EMBEDDING_SERVER` (default `http://localhost:8001`) for producing vectors

## Installation

Knowledge is a standalone CLI app rather than a library you pull into another project.

```bash
git clone https://github.com/conduit-ui/knowledge.git
cd knowledge
composer install
cp .env.example .env
```

The entry point is the `know` binary in the project root:

```bash
./know list        # show every available command
```

Optionally, build a single self-contained PHAR with [Box](https://github.com/box-project/box) (see `box.json`):

```bash
box compile        # bundle app + vendor into a single PHAR
```

## Quick Start

### 1. Start the vector database

```bash
make up            # docker compose up -d — starts Qdrant
```

This brings up Qdrant on `http://localhost:6333` (HTTP) and `6334` (gRPC). Embeddings are generated through the [`the-shit/vector`](https://packagist.org/packages/the-shit/vector) client, which talks to the embedding server configured via `QDRANT_EMBEDDING_SERVER` — run that separately or point it at an existing one.

`make status` health-checks the services; `make down` stops them; `make clean` also removes the data volume.

### 2. Initialize the collection

```bash
./know install
```

### 3. Capture and retrieve

```bash
# Git context (repo, branch, commit, author) is detected automatically
./know add "Fix Auth Timeout" \
  --content="Increase token TTL in config/auth.php" \
  --category=debugging --tags=auth,timeout

# Skip git detection
./know add "API Key Policy" --content="Store in vault, never in .env" --no-git

# Semantic search, with optional metadata filters
./know search "authentication timeout issues"
./know search "flaky tests" --category=testing --priority=high --limit=5
```

## Core Concepts

- **Pure vector storage.** There is no SQLite and there are no Eloquent models. Every entry is a point in a Qdrant collection: the vector drives search, and everything else (title, content, category, tags, confidence, git context) rides along in the JSON payload.
- **Per-project namespaces.** `ProjectDetectorService` reads your git repo and routes entries into a project-specific collection. Most commands accept `--project=<name>` to target another namespace and `--global` to search across all of them.
- **Tiered retrieval.** `TieredSearchService` searches narrow-to-wide across four tiers (working context → recent → structured → archive) and returns early on confident matches, keeping latency low.
- **Write gate.** `WriteGateService` filters low-quality and duplicate entries before they land, so the base stays signal-heavy. Use `--force` on `add` to bypass it.
- **Confidence & staleness.** `EntryMetadataService` degrades confidence over time; search results flag entries as `[STALE]` when they haven't been verified recently. Run `validate <id>` to reaffirm one.
- **Corrections, not overwrites.** `correct` supersedes an entry with a corrected version and propagates the fix to related, conflicting entries rather than destroying history.
- **Background enhancement.** `add` stays fast; Ollama auto-tagging is queued to a file-based queue and processed later by `enhance:worker`.

## AI Integration (MCP)

Knowledge registers a local [MCP](https://modelcontextprotocol.io) server named `knowledge` in `routes/ai.php`:

```php
Mcp::local('knowledge', KnowledgeServer::class);
```

Start it as a local server for your MCP client (e.g. Claude Code):

```bash
./know mcp:start knowledge
```

The server (`app/Mcp/Servers/KnowledgeServer.php`) exposes eight tools, all of which auto-detect the current project from git:

| Tool | What it does |
|------|--------------|
| `recall` | Semantic vector search with tiered retrieval, ranked by relevance, confidence, and freshness |
| `remember` | Capture a discovery — auto-detects git context, runs the write gate, checks for duplicates |
| `correct` | Supersede wrong knowledge with a corrected version and propagate the fix |
| `context` | Load project-relevant entries grouped by category; ideal at session start |
| `stats` | Entry counts, project namespaces, and system health |
| `search-code` | Semantic code search across indexed repositories |
| `file-outline` | Symbol outline of a file — classes, methods, functions with hierarchy |
| `symbol-lookup` | Look up a symbol by ID, optionally with its source code |

Point your MCP client at the local server with a command such as `./know mcp:start knowledge`. Use `./know mcp:inspector knowledge` to test the connection interactively.

## Commands

Most knowledge commands accept `--project=<name>` (target a namespace) and `--global` (span all namespaces); the project is auto-detected from git when omitted.

### Knowledge

| Command | Description |
|---------|-------------|
| `add {title}` | Add an entry (git-aware, queues async Ollama enhancement) |
| `search {query?}` | Semantic vector search with metadata filters |
| `show {id}` | Display an entry's full details |
| `entries` | List entries with `--category` / `--priority` / `--status` / `--module` filters |
| `update {id}` | Update an existing entry (`--add-tags` appends, `--tags` replaces) |
| `validate {id}` | Reaffirm an entry, boosting effective confidence |
| `archive {id}` | Soft-delete an entry (`--restore` to bring it back) |
| `correct {id}` | Correct an entry, superseding the original and propagating the fix |
| `export {id}` | Export one entry (`--format=markdown\|json`) |
| `export:all` | Bulk-export all entries to a directory |

### Intelligence

| Command | Description |
|---------|-------------|
| `context` | Load semantic session context for AI tools, capped by `--max-tokens` |
| `insights` | AI-generated insights — `--themes`, `--patterns`, or classify a single entry |
| `synthesize` | Deduplicate, digest, and archive stale entries (all `--dry-run`-able) |
| `stage` | Stage an entry in the daily log before permanent storage |
| `promote` | Promote staged entries to permanent knowledge (`--auto`, `--date`, `--all`) |
| `enhance:worker` | Process the background Ollama enhancement queue (`--once`, `--status`) |

### Code Intelligence

| Command | Description |
|---------|-------------|
| `index-code {path?}` | Index a codebase for semantic code search (`--incremental`, `--list`) |
| `search-code {query}` | Semantic search over indexed symbols (`--show-source`, `--kind`, `--file`) |
| `vectorize-code {repo}` | Vectorize tree-sitter symbols into Qdrant |
| `reindex:all` | Incrementally re-index and vectorize all git repos under a base path |
| `git:context` | Display the current git context |

### Infrastructure

| Command | Description |
|---------|-------------|
| `install` | Initialize the Qdrant collection |
| `config` | Manage configuration (`config get\|set\|list`) |
| `stats` | Analytics dashboard for the knowledge base |
| `search:status` | Search infrastructure health check |
| `agent:status` | Dependency health checks (Qdrant, Redis, Ollama, embeddings) |
| `maintain` | Run maintenance passes over entries |
| `projects` | List all project knowledge bases |
| `daemon:install` | Install/manage systemd timers for background daemons |

### Services (Docker)

| Command | Description |
|---------|-------------|
| `service:up` | Start the backing services |
| `service:down` | Stop them |
| `service:status` | Health-check all services |
| `service:logs` | Tail service logs |

### Sync

| Command | Description |
|---------|-------------|
| `sync` | Bidirectional cloud sync (`--push` / `--pull` / `--full-sync`) |
| `sync:remote` | Background sync to a centralized remote server |
| `sync:purge` | Purge the local deletion/sync queue |

## Configuration

Configuration comes from `.env` (see `.env.example`), the config files in `config/`, and an optional per-user override at `~/.knowledge/config.json` (merged in by `AppServiceProvider`).

```env
# Vector database (Qdrant)
QDRANT_ENABLED=true
QDRANT_HOST=localhost
QDRANT_PORT=6333
EMBEDDING_PROVIDER=qdrant
QDRANT_EMBEDDING_SERVER=http://localhost:8001

# Redis cache (optional — improves query speed)
REDIS_HOST=localhost
REDIS_PORT=6379

# Ollama LLM (optional — auto-tagging and query expansion)
OLLAMA_ENABLED=false
OLLAMA_HOST=localhost
OLLAMA_PORT=11434
OLLAMA_MODEL=llama3.2:3b

# Centralized sync (optional — multi-machine sharing)
REMOTE_SYNC_ENABLED=false
# REMOTE_SYNC_URL=http://your-server:8080
# REMOTE_SYNC_TOKEN=your-token

# Cloud API sync (optional)
# PREFRONTAL_API_URL=http://your-api:8080
# PREFRONTAL_API_TOKEN=your-token
```

Other notable keys (with defaults, from `config/search.php`):

| Key | Default | Purpose |
|-----|---------|---------|
| `EMBEDDING_DIMENSION` | `1024` | Vector size (`1024` for bge-large, `384` for all-MiniLM-L6-v2) |
| `SEARCH_MIN_SIMILARITY` | `0.3` | Minimum similarity score for a match |
| `SEARCH_MAX_RESULTS` | `20` | Default result cap |
| `QDRANT_COLLECTION` | `knowledge` | Base collection name |
| `QDRANT_CACHE_TTL` | `604800` | Embedding cache lifetime (7 days) |
| `HYBRID_SEARCH_ENABLED` | `false` | Combine dense + sparse (BM25) vectors via Reciprocal Rank Fusion |

### Remote server (production)

`docker-compose.remote.yml` binds services to a specific network interface (Tailscale, VPN, or LAN) so several machines can sync against one centralized knowledge base. Last-write-wins conflict resolution is based on `updated_at`.

## Testing

```bash
composer test           # Pest, run in parallel
composer test-coverage  # with a coverage report
```

Run a single file or filter:

```bash
vendor/bin/pest tests/Feature/Commands/KnowledgeSearchCommandTest.php
```

## Development

```bash
composer install        # install dependencies
composer format         # Laravel Pint (Laravel preset)
composer analyse        # PHPStan level 8, strict rules
composer test           # Pest, parallel
```

Quality gates are enforced in CI by the **Sentinel Gate** workflow: **95% coverage minimum**, **PHPStan level 8**, and Pint formatting. Keep the suite green — the gate certifies PRs before merge.

## Stack

- **Runtime** — PHP 8.2+, Laravel Zero 12
- **Vector DB** — Qdrant, via the [`the-shit/vector`](https://packagist.org/packages/the-shit/vector) connector and embedding client
- **Cache** — Redis
- **LLM** — Ollama (optional), for auto-tagging and query expansion
- **AI protocol** — [`laravel/mcp`](https://github.com/laravel/mcp) local server
- **HTTP** — Saloon (used internally by the Qdrant and code-indexing services)
- **Testing** — Pest 4
- **CI** — GitHub Actions (Sentinel Gate)

## Contributing

The workflow is test-first: write a failing test, make it pass, then run `composer format` and `composer analyse` before pushing. PRs must clear the Sentinel Gate (95% coverage, PHPStan level 8, Pint) to merge.

## License

Released under the MIT License.
