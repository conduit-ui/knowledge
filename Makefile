.PHONY: help up down logs status restart clean embed-test

# Default target
help:
	@echo "Knowledge Vector Database Management"
	@echo ""
	@echo "Usage:"
	@echo "  make up        - Start Qdrant and embedding server"
	@echo "  make down      - Stop all services"
	@echo "  make logs      - Tail service logs"
	@echo "  make status    - Check service status"
	@echo "  make restart   - Restart all services"
	@echo "  make clean     - Stop services and remove volumes"
	@echo "  make embed-test - Test embedding server"
	@echo ""

# Start services
up:
	@echo "Starting Qdrant and embedding server..."
	@docker compose up -d
	@echo "Waiting for services to be healthy..."
	@sleep 5
	@docker compose ps
	@echo ""
	@echo "Services ready!"
	@echo "  Qdrant:     http://localhost:6333"
	@echo "  Embeddings: http://localhost:8001"
	@echo ""
	@echo "Enable in .env:"
	@echo "  SEMANTIC_SEARCH_ENABLED=true"

# Stop services
down:
	@docker compose down

# View logs
logs:
	@docker compose logs -f

# Check status
status:
	@docker compose ps
	@echo ""
	@echo "Health checks:"
	@curl -sf http://localhost:6333/collections > /dev/null && echo "  Qdrant:     OK" || echo "  Qdrant:     NOT RUNNING"
	@curl -sf http://localhost:8001/health > /dev/null && echo "  Embeddings: OK" || echo "  Embeddings: NOT RUNNING"

# Restart services
restart:
	@docker compose restart

# Clean up (removes data volumes)
clean:
	@echo "Stopping services and removing volumes..."
	@docker compose down -v
	@echo "Done. All data has been removed."

# Test embedding server
embed-test:
	@echo "Testing embedding server..."
	@curl -s -X POST http://localhost:8001/embed \
		-H "Content-Type: application/json" \
		-d '{"text": "Hello world"}' | jq '.dimension, .model'
