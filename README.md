# Knowledge

AI-powered knowledge base with semantic search and ChromaDB integration.

## Vision

Build an intelligent knowledge management system that:
- **Semantic Search**: Find knowledge by meaning, not just keywords (ChromaDB)
- **AI-Powered**: Confidence scoring, relevance ranking, smart suggestions
- **Git Integration**: Automatic context capture, knowledge attribution, evolution tracking
- **Test-Driven**: 100% coverage enforced via Synapse Sentinel
- **Quality-First**: Maximum static analysis (PHPStan level 8)

## Features

### Git Context Integration

Automatically capture and track git metadata for knowledge attribution:

- **Auto-detection**: Automatically captures repo, branch, commit, and author when adding entries
- **Knowledge Attribution**: "Git blame" for knowledge - see who documented what and when
- **Evolution Tracking**: Track how knowledge changes across commits and branches
- **Cross-repo Support**: Store full repository URLs for knowledge shared across projects

#### Commands

```bash
# Add entry with automatic git context detection
./know knowledge:add "Fix Database Connection" --content="Always check .env configuration"

# Skip git detection for sensitive repositories
./know knowledge:add "API Keys" --content="Store in vault" --no-git

# Manual git field overrides
./know knowledge:add "Config" --content="..." --repo="custom/repo" --branch="main"

# Display current git context
./know knowledge:git:context

# List entries from a specific commit
./know knowledge:git:entries abc123def456

# List entries by author
./know knowledge:git:author "John Doe"
```

#### Auto-Detection Behavior

When you run `knowledge:add` in a git repository:

1. **Repository**: Detects remote origin URL (falls back to local path)
2. **Branch**: Captures current branch name
3. **Commit**: Records current commit hash (full SHA-1)
4. **Author**: Uses git config user.name

To disable auto-detection, use the `--no-git` flag.

#### Manual Overrides

You can override auto-detected values:

```bash
./know knowledge:add "Title" \
  --content="Content here" \
  --repo="https://github.com/org/repo" \
  --branch="feature/new-feature" \
  --commit="abc123def456" \
  --author="Jane Smith"
```

#### Non-Git Directories

The tool gracefully handles non-git directories:
- No errors or warnings
- Git fields remain null
- All other functionality works normally

#### Git Hooks Integration

You can integrate with git hooks for automatic knowledge capture:

**Post-commit hook example** (`.git/hooks/post-commit`):

```bash
#!/bin/bash
# Automatically prompt for knowledge entry after each commit

echo "Would you like to document this commit? (y/n)"
read -r response

if [[ "$response" =~ ^[Yy]$ ]]; then
    ./know knowledge:add "$(git log -1 --pretty=%s)" \
        --content="$(git log -1 --pretty=%B)" \
        --category=debugging
fi
```

**Pre-push hook example** (`.git/hooks/pre-push`):

```bash
#!/bin/bash
# Check for undocumented commits

UNDOCUMENTED=$(git log origin/main..HEAD --format="%H" | while read commit; do
    if ! ./know knowledge:git:entries "$commit" | grep -q "Total entries: 0"; then
        echo "$commit"
    fi
done)

if [ -n "$UNDOCUMENTED" ]; then
    echo "Warning: Some commits lack knowledge entries"
    echo "$UNDOCUMENTED"
fi
```

### Confidence Scoring & Usage Analytics

Track knowledge quality and usage over time:

```bash
# Validate an entry (boosts confidence)
./know knowledge:validate 1

# View stale entries needing review
./know knowledge:stale

# Display analytics dashboard
./know knowledge:stats
```

### Collections

Organize knowledge into logical groups:

```bash
# Create a collection
./know knowledge:collection:create "Deployment Runbook" --description="Production deployment steps"

# Add entries to collection
./know knowledge:collection:add "Deployment Runbook" 1
./know knowledge:collection:add "Deployment Runbook" 2 --sort-order=10

# View collection
./know knowledge:collection:show "Deployment Runbook"

# List all collections
./know knowledge:collection:list
```

### Search & Discovery

```bash
# Search by keyword
./know knowledge:search --keyword="database"

# Search by tag
./know knowledge:search --tag="sql"

# Search by category and priority
./know knowledge:search --category="debugging" --priority="critical"

# List entries with filters
./know knowledge:list --category=testing --limit=10 --min-confidence=75
```

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Format code
composer format

# Static analysis
composer analyse

# Run single test file
vendor/bin/pest tests/Feature/GitContextServiceTest.php
```

## Quality Standards

- **Test Coverage**: 100% (enforced by Synapse Sentinel)
- **Static Analysis**: PHPStan Level 8
- **Code Style**: Laravel Pint (Laravel preset)
- **Auto-Merge**: PRs auto-merge after certification

## Architecture

### Services

**GitContextService** - Git metadata detection and retrieval
- Detects git repositories using `git rev-parse`
- Retrieves branch, commit, author information
- Handles non-git directories gracefully
- Supports custom working directories for testing

**ConfidenceService** - Knowledge quality scoring
- Age-based confidence decay
- Validation boosts
- Stale entry detection

**CollectionService** - Knowledge organization
- Create and manage collections
- Add/remove entries with sort ordering
- Duplicate prevention

### Models

- **Entry** - Knowledge entries with git metadata, confidence scores, usage tracking
- **Collection** - Groups of related entries
- **Tag** - Normalized tags with usage counts
- **Relationship** - Links between entries (related-to, supersedes, depends-on)

## Status

Active development. Core features implemented:
- Database schema and models
- Basic CLI commands
- Collections support
- Git context integration
- Confidence scoring and analytics

### Semantic Search with ChromaDB

Advanced vector-based semantic search for finding knowledge by meaning:

#### Installation

ChromaDB requires Python 3.8+ and a ChromaDB server. Install using:

```bash
# Install ChromaDB server
pip install chromadb

# Start ChromaDB server (default: localhost:8000)
chroma run --path ./chroma_data
```

For production, you can also run ChromaDB in Docker:

```bash
docker run -d -p 8000:8000 chromadb/chroma
```

#### Embedding Server

You'll also need an embedding server. We recommend using a simple Flask server with sentence-transformers:

```bash
# Install dependencies
pip install flask sentence-transformers

# Create embedding_server.py
cat > embedding_server.py << 'EOF'
from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer

app = Flask(__name__)
model = SentenceTransformer('all-MiniLM-L6-v2')

@app.route('/embed', methods=['POST'])
def embed():
    data = request.json
    text = data.get('text', '')
    embedding = model.encode(text).tolist()
    return jsonify({'embedding': embedding})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8001)
EOF

# Run embedding server
python embedding_server.py
```

#### Configuration

Enable ChromaDB in your `.env` file:

```env
SEMANTIC_SEARCH_ENABLED=true
EMBEDDING_PROVIDER=chromadb
CHROMADB_ENABLED=true
CHROMADB_HOST=localhost
CHROMADB_PORT=8000
CHROMADB_EMBEDDING_SERVER=http://localhost:8001
CHROMADB_EMBEDDING_MODEL=all-MiniLM-L6-v2
```

#### Usage

Once configured, semantic search automatically works with the existing search commands:

```bash
# Semantic search will automatically be used
./know knowledge:search --keyword="database connection issues"

# Results are ranked by semantic similarity and confidence
# Falls back to keyword search if ChromaDB is unavailable
```

#### How It Works

1. When you add/update entries, embeddings are generated and stored in both:
   - ChromaDB vector database (for fast similarity search)
   - SQLite database (as JSON, for fallback)

2. Search queries are:
   - Converted to embedding vectors
   - Compared against indexed entries using cosine similarity
   - Ranked by: `similarity_score * (confidence / 100)`
   - Filtered by metadata (category, tags, status, etc.)

3. If ChromaDB is unavailable:
   - Automatically falls back to SQLite-based semantic search
   - Or keyword search if no embeddings are available

#### Architecture

**ChromaDBClient** - ChromaDB REST API client
- Collection management
- Document indexing (add/update/delete)
- Vector similarity search

**ChromaDBEmbeddingService** - Text embedding generation
- Generates embedding vectors using specified model
- Calculates cosine similarity between vectors
- Graceful error handling

**ChromaDBIndexService** - Index management
- Indexes entries on create/update
- Removes entries on delete
- Batch indexing support
- Automatic embedding generation and storage

**SemanticSearchService** - Hybrid search orchestration
- ChromaDB search (when available)
- SQLite fallback search
- Keyword search fallback
- Metadata filtering (category, tags, module, priority, status)

Coming soon:
- Export and publishing
- Web interface

## License

MIT License. See [LICENSE](LICENSE) for details.
