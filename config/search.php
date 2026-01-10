<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Semantic Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configure semantic search capabilities for the knowledge base.
    | Supports ChromaDB integration for advanced semantic search.
    |
    */

    'semantic_enabled' => env('SEMANTIC_SEARCH_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Embedding Provider
    |--------------------------------------------------------------------------
    |
    | The embedding provider to use for generating text embeddings.
    | Supported: "none", "chromadb", "qdrant"
    |
    */

    'embedding_provider' => env('EMBEDDING_PROVIDER', 'qdrant'),

    /*
    |--------------------------------------------------------------------------
    | Full-Text Search Provider
    |--------------------------------------------------------------------------
    |
    | The full-text search provider to use for observation search.
    | Supported: "sqlite", "stub"
    |
    */

    'fts_provider' => env('FTS_PROVIDER', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Vector Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure vector database connection settings.
    |
    */

    'chromadb' => [
        'enabled' => env('CHROMADB_ENABLED', false),
        'host' => env('CHROMADB_HOST', 'localhost'),
        'port' => env('CHROMADB_PORT', 8000),
        'embedding_server' => env('CHROMADB_EMBEDDING_SERVER', 'http://localhost:8001'),
        'model' => env('CHROMADB_EMBEDDING_MODEL', 'all-MiniLM-L6-v2'),
    ],

    'qdrant' => [
        'enabled' => env('QDRANT_ENABLED', true),
        'host' => env('QDRANT_HOST', 'localhost'),
        'port' => env('QDRANT_PORT', 6333),
        'api_key' => env('QDRANT_API_KEY', null),
        'embedding_server' => env('QDRANT_EMBEDDING_SERVER', 'http://localhost:8001'),
        'model' => env('QDRANT_EMBEDDING_MODEL', 'all-MiniLM-L6-v2'),
        'collection' => env('QDRANT_COLLECTION', 'knowledge'),
        // Redis caching for embeddings and search results
        'cache_embeddings' => env('QDRANT_CACHE_EMBEDDINGS', true),
        'cache_ttl' => env('QDRANT_CACHE_TTL', 604800), // 7 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Dimension
    |--------------------------------------------------------------------------
    |
    | The dimension of the embedding vectors.
    | all-MiniLM-L6-v2: 384
    | OpenAI text-embedding-ada-002: 1536
    | OpenAI text-embedding-3-small: 1536
    | OpenAI text-embedding-3-large: 3072
    |
    */

    'embedding_dimension' => env('EMBEDDING_DIMENSION', 384),

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configure search behavior and thresholds.
    |
    */

    'minimum_similarity' => env('SEARCH_MIN_SIMILARITY', 0.7),
    'max_results' => env('SEARCH_MAX_RESULTS', 20),

    /*
    |--------------------------------------------------------------------------
    | Ollama Configuration
    |--------------------------------------------------------------------------
    |
    | Local LLM for entry enhancement, tagging, and concept extraction.
    |
    */

    'ollama' => [
        'enabled' => env('OLLAMA_ENABLED', true),
        'host' => env('OLLAMA_HOST', 'localhost'),
        'port' => env('OLLAMA_PORT', 11434),
        'model' => env('OLLAMA_MODEL', 'llama3.2:3b'), // Fast, good for structured output
        'timeout' => env('OLLAMA_TIMEOUT', 30),

        // Features to enable
        'auto_tag' => env('OLLAMA_AUTO_TAG', true),
        'auto_categorize' => env('OLLAMA_AUTO_CATEGORIZE', true),
        'extract_concepts' => env('OLLAMA_EXTRACT_CONCEPTS', true),
        'suggest_relationships' => env('OLLAMA_SUGGEST_RELATIONSHIPS', true),
        'enhance_queries' => env('OLLAMA_ENHANCE_QUERIES', true),
    ],
];
