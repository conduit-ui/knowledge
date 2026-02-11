# Knowledge CLI - Mission & Vision

## Mission Statement

**Build the fastest, smartest semantic knowledge base that automatically feeds perfect context to AI tools.**

Knowledge is a command-line tool that captures technical decisions, learnings, and context from your work, then intelligently retrieves exactly what you need when you need it - especially for AI pair programming with Claude Code.

## Core Principles

### 1. AI-First Context Engine
- **Primary use case**: Feed Claude Code/LLMs with relevant background automatically
- **Smart retrieval**: Ollama-powered query expansion understands intent
- **Sub-200ms search**: Instant results through aggressive Redis caching
- **Project-aware**: Auto-detects git repo, returns project-specific context

### 2. Offline-First, Sync-Later
- **Local Qdrant**: Full functionality without network
- **Background sync**: Queue writes, sync to remote server every N minutes
- **Read-optimized**: Instant local reads, async writes to central DB
- **Graceful degradation**: Always functional, network optional

### 3. Zero-Friction Capture
- **Multiple entry points**: CLI commands, git hooks, Claude Code hooks, imports
- **Background enhancement**: Ollama auto-tags/categorizes async
- **No manual organization**: Project namespaces auto-detected from git
- **Write and forget**: Add instantly, enhancement happens later

### 4. Pure Vector Architecture
- **Qdrant only**: No SQLite, no schema migrations
- **Payload-based metadata**: Store everything as JSON in vector payloads
- **Redis caching**: Embeddings, queries, results all cached
- **Simple**: One docker-compose up, you're running

## What We're NOT Building

❌ Session tracking / time management
❌ Focus blocks / productivity features
❌ Manual priority/blocker/milestone tracking
❌ Complex relational schemas
❌ Interactive TUI / REPL modes

## The Vision: 100x Productivity

**Before Knowledge:**
- Forget why decisions were made
- Re-learn context every time you switch projects
- Manually paste background into AI chats
- Slow, keyword-based search misses relevant info

**After Knowledge:**
- Semantic search instantly recalls past decisions
- Auto-injected project context for every AI conversation
- Background intelligence organizes everything automatically
- Sub-200ms queries from local cache, synced to team

## Success Metrics

1. **Speed**: < 200ms for any query (90th percentile)
2. **Accuracy**: Relevant results in top 3 (measured by click-through)
3. **Adoption**: Used daily by every team member
4. **AI Integration**: 80%+ of Claude Code sessions use knowledge context
5. **Coverage**: All major decisions/learnings captured within 24 hours

## Architecture Stack

```
┌─────────────────────────────────────────┐
│  CLI (Laravel Zero)                     │
│  - One-shot commands                    │
│  - Scriptable / hookable                │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│  Local Services (Docker)                │
│  - Qdrant (vectors + all data)          │
│  - Redis (cache everything)             │
│  - Embeddings (Python/FastAPI)          │
│  - Ollama (native, Metal GPU)           │
└──────────────┬──────────────────────────┘
               │
               │ Background sync
               ▼
┌─────────────────────────────────────────┐
│  Remote Server (Centralized)             │
│  - Same stack as local                  │
│  - Team/shared knowledge repository     │
│  - Exposed via VPN, Tailscale, or LAN   │
└─────────────────────────────────────────┘
```

## Target User Journey

**Developer working on bug fix:**
```bash
# Claude Code hook auto-captures context
git commit -m "Fix auth timeout"
# → Automatically creates knowledge entry

# Later, working on related issue
know search "authentication timeout"
# → Returns past decision in < 200ms
# → Auto-copied to clipboard for pasting into Claude

# Or better - Claude Code hook auto-injects
claude "help me debug this auth issue"
# → Knowledge CLI provides context automatically
# → Claude sees your past auth decisions
```

**That's the magic**: Knowledge becomes invisible infrastructure that makes AI conversations dramatically better.

---

*Built with Qdrant (Rust), Redis, Ollama, and Laravel Zero*
