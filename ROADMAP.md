# Knowledge CLI - 100x Productivity Roadmap

## Critical Path: 5 Issues to Ship

These 5 issues transform knowledge from "productivity tracker with ChromaDB" to "AI-first semantic context engine."

---

## Issue #1: Pure Qdrant Vector Storage

**Goal**: Replace SQLite entirely with Qdrant-only architecture

**Why**: Eliminate schema complexity, migrations, and dual-database maintenance

**Tasks**:
- [ ] Add `qdrant/php-client` composer package
- [ ] Create `QdrantService` with collection management
- [ ] Store full entry data in Qdrant payloads (no SQLite)
- [ ] Implement upsert/delete/query operations
- [ ] Create Qdrant collection per project namespace (auto-detected from git)
- [ ] Delete all SQLite migrations and models
- [ ] Update all commands to use QdrantService instead of Eloquent

**Success Criteria**:
- `know add` stores directly to Qdrant
- `know search` queries Qdrant only
- Zero SQL queries in codebase
- Migrations directory deleted

**Files to modify**:
- `app/Services/QdrantService.php` (new)
- `app/Commands/KnowledgeAddCommand.php`
- `app/Commands/KnowledgeSearchCommand.php`
- Delete: `database/migrations/*`, `app/Models/*`

---

## Issue #2: Redis Caching Layer

**Goal**: Sub-200ms query responses through aggressive caching

**Why**: Read-optimized for instant AI context injection

**Tasks**:
- [ ] Cache embeddings (key: hash of text, TTL: 7 days)
- [ ] Cache search results (key: query hash, TTL: 1 hour)
- [ ] Cache Qdrant collection stats (TTL: 5 minutes)
- [ ] Implement cache warming on startup
- [ ] Add cache invalidation on entry updates
- [ ] Monitor cache hit rates in `know stats`

**Success Criteria**:
- Cached query < 50ms (90th percentile)
- Uncached query < 200ms (90th percentile)
- 80%+ cache hit rate in normal usage
- `know stats` shows cache metrics

**Files to modify**:
- `app/Services/CacheService.php` (new)
- `app/Services/EmbeddingService.php`
- `app/Commands/KnowledgeSearchCommand.php`
- `app/Commands/KnowledgeStatsCommand.php`

---

## Issue #3: Background Ollama Enhancement

**Goal**: Auto-tag and enhance entries without blocking writes

**Why**: Instant writes, smart organization happens async

**Tasks**:
- [ ] Create enhancement queue (Redis-backed)
- [ ] Background worker processes queue
- [ ] Ollama generates: tags, category, concepts, summary
- [ ] Store enhancements back to Qdrant payload
- [ ] Skip enhancement if Ollama unavailable (degrade gracefully)
- [ ] Add `--skip-enhance` flag for fast writes
- [ ] Show enhancement status in `know show <id>`

**Success Criteria**:
- `know add` returns in < 100ms
- Enhancement completes within 10 seconds
- Entries enhanced even if Ollama is slow
- Graceful degradation when Ollama offline

**Files to modify**:
- `app/Services/EnhancementQueue.php` (new)
- `app/Services/OllamaService.php` (fix bugs)
- `app/Commands/KnowledgeAddCommand.php`
- `app/Commands/KnowledgeShowCommand.php`

---

## Issue #4: Smart Query Expansion

**Goal**: Ollama-powered semantic query understanding

**Why**: Find relevant knowledge even with imperfect queries

**Tasks**:
- [ ] Expand user query with synonyms/related terms via Ollama
- [ ] Generate multiple embedding variations
- [ ] Query Qdrant with all variations
- [ ] Merge and de-duplicate results
- [ ] Rank by semantic similarity + recency
- [ ] Show "searched for: X, Y, Z" to user
- [ ] Cache expanded queries (Redis)

**Success Criteria**:
- `know search "redis"` finds entries about "cache", "key-value store"
- Relevant results even with typos or informal language
- Query expansion < 500ms (cached) or < 2s (uncached)
- Top 3 results are relevant 80%+ of the time

**Files to modify**:
- `app/Services/QueryExpansionService.php` (new)
- `app/Services/OllamaService.php`
- `app/Commands/KnowledgeSearchCommand.php`

---

## Issue #5: Project-Aware Namespacing

**Goal**: Auto-detect git repo, create per-project knowledge collections

**Why**: Context-specific results, no noise from other projects

**Tasks**:
- [ ] Detect git repo name from `git remote -v`
- [ ] Create Qdrant collection: `knowledge_{repo_name}`
- [ ] Auto-switch collection based on current directory
- [ ] Add `--project=` flag to override
- [ ] Add `--global` flag to search all projects
- [ ] Show current project in `know stats`
- [ ] List all projects in `know projects` (new command)

**Success Criteria**:
- Each git repo gets its own namespace automatically
- `know search` only returns results from current project
- `know search --global` searches all projects
- `know projects` lists all knowledge bases

**Files to modify**:
- `app/Services/ProjectDetectionService.php` (new)
- `app/Services/QdrantService.php`
- All search/add commands
- `app/Commands/ProjectsCommand.php` (new)

---

## Bonus Issue #6: Odin Sync (Post-MVP)

**Goal**: Background sync to centralized Odin server

**Why**: Team knowledge sharing, backup, multi-machine access

**Tasks**:
- [ ] Background sync queue (writes to Odin Qdrant)
- [ ] Conflict resolution (last-write-wins)
- [ ] Pull fresh results during search if Odin available
- [ ] `know sync` command for manual sync
- [ ] Show sync status in `know stats`

**Success Criteria**:
- Writes queue for sync every 5 minutes
- Search checks Odin for fresh results
- Fully functional offline
- Team members see each other's knowledge

---

## Implementation Order

1. **Issue #1** (Qdrant) - Foundation, blocks everything else
2. **Issue #5** (Projects) - Must happen before adding real data
3. **Issue #2** (Caching) - Performance boost
4. **Issue #3** (Enhancement) - Auto-organization
5. **Issue #4** (Query expansion) - Smart search
6. **Issue #6** (Sync) - Team collaboration

## Timeline Estimate

- Week 1: Issue #1 + #5 (Foundation)
- Week 2: Issue #2 + #3 (Performance + Intelligence)
- Week 3: Issue #4 (Smart search)
- Week 4: Issue #6 (Sync to Odin)

**MVP: Issues #1, #2, #5** = Functional vector knowledge base
**100x Productivity: All 6 issues** = AI-first context engine

---

*Next step: Delete productivity commands, implement Issue #1*
