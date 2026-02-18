# Knowledge CLI Setup Guides

Quick start guides for different development scenarios.

## 1. Quick Local Setup (Solo Developer)

**Perfect for**: Personal knowledge management, learning projects

### 1. Start Services

```bash
# Using make (recommended)
make up

# Or manually
docker compose up -d
```

**What starts:**
- **Qdrant** (Vector DB): `http://localhost:6333`
- **Embedding Server**: `http://localhost:8001`

### 2. Initialize

```bash
# Initialize with default project
./know install

# Check installation
./know agent:status
```

### 3. Add Your First Knowledge

```bash
# From current git repo (auto-detects context)
./know add "API Authentication Fix" --content="Use JWT tokens instead of sessions for API calls" --tags=authentication,api

# Manual entry without git context
./know add "Personal Note" --content="Remember to backup configuration files weekly" --no-git
```

### 4. Test Search

```bash
# Semantic search
./know search "authentication issues"

# Search with filters
./know search --tag=api --limit=3
```

---

## 2. Team/Shared Setup

**Perfect for**: Small teams, shared knowledge base

### 1. Centralized Server Setup

```bash
# On server machine
git clone https://github.com/conduit-ui/knowledge.git
cd knowledge

# Configure for remote access
export BIND_ADDR=192.168.1.100  # Your server IP
docker compose -f docker-compose.remote.yml up -d
```

### 2. Team Member Configuration

```bash
# On each developer machine
export QDRANT_HOST=192.168.1.100
export REDIS_HOST=192.168.1.100
export EMBEDDING_SERVER_URL=http://192.168.1.100:8001

# Initialize with shared collection
./know install --project=team-knowledge
```

### 3. Team Usage Patterns

```bash
# Team member adds knowledge
./know add "Database Migration Process" --content="Run migrations in --step mode for review" --tags=database,team --project=team-knowledge

# Search across team knowledge
./know search "migration process" --project=team-knowledge

# Global search across all projects
./know search "database patterns" --global
```

---

## 3. AI-Enhanced Setup

**Perfect for**: Advanced users wanting automatic tagging and insights

### 1. Install Ollama

```bash
# Install Ollama
curl -fsSL https://ollama.ai/install.sh | sh

# Pull a model
ollama pull llama2

# Start Ollama service
ollama serve
```

### 2. Configure AI Features

```bash
# Add to .env file
echo "OLLAMA_HOST=http://localhost:11434" >> .env
echo "OLLAMA_MODEL=llama2" >> .env
```

### 3. Enable AI Enhancement

```bash
# Add knowledge (will be auto-tagged by AI)
./know add "Performance Issue" --content="Cache database queries in Redis for better performance"

# Process enhancement queue
./know enhance:worker

# Get AI insights
./know insights --topic=performance
```

---

## 4. Development Environment Setup

**Perfect for**: Developers working on Knowledge CLI itself

### 1. Clone and Install Dependencies

```bash
git clone https://github.com/conduit-ui/knowledge.git
cd knowledge

composer install
```

### 2. Development Services

```bash
# Start services for development
make up

# Run tests
composer test

# Check code quality
composer format
composer analyse
```

### 3. Development Workflow

```bash
# Make changes
# ...

# Test changes
composer test -- --filter="YourTest"

# Check code quality
composer analyse

# Commit with proper message
git commit -m "feat: add new feature"
```

---

## 5. Docker Development Setup

**Perfect for**: Containerized development environments

### 1. Development Container

```dockerfile
# Dockerfile.dev
FROM php:8.2-cli

# Install dependencies
RUN apt-get update && apt-get install -y \
    git curl unzip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Knowledge CLI
WORKDIR /app
COPY . .
RUN composer install --no-dev

# Setup environment
ENV QDRANT_HOST=host.docker.internal
ENV REDIS_HOST=host.docker.internal
ENV EMBEDDING_SERVER_URL=http://host.docker.internal:8001

CMD ["bash"]
```

### 2. Docker Compose Development

```yaml
# docker-compose.dev.yml
version: '3.8'
services:
  knowledge-dev:
    build:
      context: .
      dockerfile: Dockerfile.dev
    volumes:
      - .:/app
    environment:
      - QDRANT_HOST=qdrant
      - REDIS_HOST=redis
      - EMBEDDING_SERVER_URL=http://embedding-server:8001
    depends_on:
      - qdrant
      - redis
      - embedding-server
    command: bash -c "cd /app && ./know install && tail -f /dev/null"

  qdrant:
    image: qdrant/qdrant:latest
    ports:
      - "6333:6333"
    volumes:
      - qdrant_dev:/qdrant/storage

  redis:
    image: redis:alpine
    ports:
      - "6379:6379"

  embedding-server:
    build:
      context: ./docker/embedding-server
    ports:
      - "8001:8001"
    depends_on:
      - qdrant

volumes:
  qdrant_dev:
```

### 3. Usage

```bash
# Start development environment
docker compose -f docker-compose.dev.yml up -d

# Enter development container
docker compose -f docker-compose.dev.yml exec knowledge-dev bash

# Use Knowledge CLI
./know add "Docker Development" --content="Running in Docker containers for consistency"
```

---

## 6. Production Setup

**Perfect for**: Production deployment, centralized knowledge server

### 1. Production Docker Compose

```yaml
# docker-compose.prod.yml
version: '3.8'
services:
  qdrant:
    image: qdrant/qdrant:latest
    restart: always
    ports:
      - "6333:6333"
      - "6334:6334"
    volumes:
      - qdrant_data:/qdrant/storage
    environment:
      - QDRANT__SERVICE__HTTP_PORT=6333
      - QDRANT__SERVICE__GRPC_PORT=6334
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:6333/health"]
      interval: 30s
      timeout: 10s
      retries: 3

  redis:
    image: redis:7-alpine
    restart: always
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    command: redis-server --appendonly yes --maxmemory 512mb --maxmemory-policy allkeys-lru

  embedding-server:
    build:
      context: ./docker/embedding-server
    restart: always
    ports:
      - "8001:8001"
    volumes:
      - embedding_cache:/root/.cache
    environment:
      - EMBEDDING_MODEL=BAAI/bge-large-en-v1.5
      - DEVICE=cpu
    depends_on:
      - qdrant

volumes:
  qdrant_data:
    driver: local
  redis_data:
    driver: local
  embedding_cache:
    driver: local
```

### 2. Production Configuration

```bash
# Production .env
QDRANT_HOST=localhost
QDRANT_PORT=6333
REDIS_HOST=localhost
REDIS_PORT=6379
EMBEDDING_SERVER_URL=http://localhost:8001

# Performance tuning
SEARCH_LIMIT=50
SEARCH_EMBEDDING_DIMENSION=1024
SEARCH_MINIMUM_SIMILARITY=0.7

# Write gate (quality control)
WRITE_GATE_ENABLED=true
```

### 3. Deployment

```bash
# Deploy to production
docker compose -f docker-compose.prod.yml up -d

# Verify deployment
./know agent:status
./know stats
```

---

## 7. Migration from Other Tools

**Perfect for**: Moving from existing knowledge management systems

### 1. Export from Existing Systems

```bash
# Export from Notion (using API)
# Export from Confluence (using API)
# Export from personal notes
```

### 2. Import to Knowledge CLI

```bash
# Create migration script
cat > migrate.sh << 'EOF'
#!/bin/bash

# Read from existing system
while IFS= read -r line; do
    title=$(echo "$line" | cut -d'|' -f1)
    content=$(echo "$line" | cut -d'|' -f2)
    tags=$(echo "$line" | cut -d'|' -f3)
    
    # Add to Knowledge CLI
    ./know add "$title" --content="$content" --tags="$tags"
done < existing_knowledge.txt
EOF

chmod +x migrate.sh
./migrate.sh
```

---

## Quick Reference

### Essential Commands

```bash
# Setup
make up                    # Start services
./know install             # Initialize

# Daily use
./know add "Title" --content="..."    # Add knowledge
./know search "query"               # Search
./know show <id>                   # Show details

# Maintenance
./know agent:status                 # Health check
./know stats                       # Statistics
./know maintain                    # Maintenance
```

### Environment Variables

```bash
# Required
QDRANT_HOST=localhost
QDRANT_PORT=6333
REDIS_HOST=localhost
REDIS_PORT=6379
EMBEDDING_SERVER_URL=http://localhost:8001

# Optional
OLLAMA_HOST=http://localhost:11434    # AI features
WRITE_GATE_ENABLED=true               # Quality control
SEARCH_LIMIT=20                     # Results limit
```

### Troubleshooting

| Issue | Solution |
|-------|----------|
| Services not starting | Check Docker, run `make status` |
| Search returns empty | Verify Qdrant running, check `./know agent:status` |
| Embeddings failing | Check embedding server at http://localhost:8001/health |
| Git context missing | Run from git repo or use `--no-git` |
| Permission denied | Check Docker permissions, user in docker group |

---

## Next Steps

After setup:
1. **Add daily learnings**: `./know add "Today's discovery" --content="..."`
2. **Search semantically**: `./know search "how to fix..."`
3. **Enable AI features**: Install Ollama for auto-tagging
4. **Team sharing**: Use remote setup for collaboration

For detailed command reference, see `./know list`.