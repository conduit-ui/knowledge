# Knowledge CLI - Command Cleanup Plan

## Commands to KEEP (Core Vector Knowledge)

### Storage & Retrieval
- `add` - Add knowledge entry
- `search` - Semantic vector search
- `show` - Display entry details
- `list` - List entries with filters

### Relationships
- `link` - Create relationships
- `unlink` - Remove relationships
- `related` - Show related entries
- `graph` - Visualize relationships

### Quality & Maintenance
- `duplicates` - Find duplicates
- `conflicts` - Detect conflicts
- `stale` - Find outdated entries
- `prune` - Cleanup old data
- `merge` - Merge entries
- `archive` - Soft delete
- `deprecate` - Mark deprecated

### Export & Sync
- `export` - Export entries
- `publish` - Static HTML export
- `sync` - Sync to Odin/prefrontal-cortex

### Infrastructure
- `service:up` - Start services
- `service:down` - Stop services
- `service:status` - Health check
- `service:logs` - View logs
- `install` - Initialize DB
- `config` - Manage config
- `index` - Build search index
- `stats` - Analytics

### Collections
- `collection:create` - Create collection
- `collection:add` - Add to collection
- `collection:remove` - Remove from collection
- `collection:show` - Show collection
- `collection:list` - List collections

## Commands to DELETE (Productivity Cruft)

### Session Tracking (Obsolete with vector search)
- `session:start` - ❌ Delete
- `session:end` - ❌ Delete
- `session:show` - ❌ Delete
- `session:list` - ❌ Delete
- `session:observations` - ❌ Delete

### Productivity Features (Not core to knowledge storage)
- `focus-time` - ❌ Delete
- `milestones` - ❌ Delete
- `blockers` - ❌ Delete
- `intents` - ❌ Delete
- `priorities` - ❌ Delete
- `context` - ❌ Delete (redundant with search)

### Observations (Redundant with vector entries)
- `observe:add` - ❌ Delete

## Rationale

**Why delete session/productivity features?**
- Overcomplicated the core mission
- Vector search provides better context retrieval
- Session tracking doesn't align with "semantic knowledge base"
- Just store observations as regular knowledge entries
- Let Ollama extract concepts/priorities automatically

**Simple architecture:**
```
Store anything → Vector DB → Semantic search gets context
```

No need for:
- Time tracking
- Session boundaries
- Manual priority setting
- Blocker tracking

All of this becomes emergent from good vector search + Ollama enhancement.
