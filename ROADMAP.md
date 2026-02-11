# Knowledge CLI - Roadmap

## Completed

### Pure Qdrant Vector Storage
Replaced SQLite entirely with Qdrant-only architecture. No schema migrations, no Eloquent models. All data stored as vector payloads.

### Redis Caching Layer
KnowledgeCacheService provides sub-200ms query responses through aggressive caching of embeddings, search results, and collection stats.

### Odin Background Sync
OdinSyncService syncs knowledge to centralized Odin server. Includes deletion propagation, sync purge, and bidirectional push/pull.

### Entry Metadata & Staleness Detection
EntryMetadataService tracks entry freshness with confidence degradation over time. Superseded marking instead of destructive overwrites.

### Write Gate
WriteGateService filters knowledge before persistence — prevents low-quality or duplicate entries from polluting the knowledge base.

### Correction Protocol
Multi-tier correction propagation. When knowledge is corrected, related entries are identified and updated.

### Daily Log Staging
DailyLogService stages entries before permanent storage. Entries can be reviewed and promoted via `stage` and `promote` commands.

### Service Management
Full Docker service lifecycle: `service:up`, `service:down`, `service:status`, `service:logs`.

### Code Indexing
Index and search codebases semantically via `index-code` and `search-code`.

### Context Command
Semantic session context loading for AI tools — auto-injects relevant knowledge into Claude Code sessions.

---

## Open PRs (Need Rebase)

### Tiered Search (#120)
Narrow-to-wide retrieval across four tiers: working context, recent, structured, archive. Early return on confident matches. **Status: merge conflict.**

### Background Ollama Auto-Tagging (#118)
Async auto-tagging via OllamaService with file-based enhancement queue. `know add` stays fast (<100ms), enhancement happens in background. **Status: merge conflict.**

### CodeRabbit Review Extraction (#117)
Extract CodeRabbit review findings from GitHub PRs and store as knowledge entries. **Status: CI failing (94.5% coverage, needs 95%).**

### Project-Aware Namespacing (#109)
Auto-detect git repo and create per-project Qdrant collections. `--project` and `--global` flags on all commands. **Status: merge conflict.**

---

## Future

- **Smart Query Expansion**: Ollama-powered semantic query understanding (synonyms, related terms)
- **Tiered Search**: Merge PR #120 — narrow-to-wide retrieval strategy
- **Background Ollama Enhancement**: Merge PR #118 — async auto-tagging
- **Project Namespacing**: Merge PR #109 — per-project collections
- **PostgreSQL/Pluggable Vector Store**: Support alternative vector backends (#23)
- **Agentify**: AI agents that monitor Claude Code conversations via hooks (#96)
