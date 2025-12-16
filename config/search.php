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
    | Supported: "none", "chromadb"
    |
    */

    'embedding_provider' => env('EMBEDDING_PROVIDER', 'none'),

    /*
    |--------------------------------------------------------------------------
    | ChromaDB Configuration
    |--------------------------------------------------------------------------
    |
    | Configure ChromaDB connection settings for vector database integration.
    |
    */

    'chromadb' => [
        'enabled' => env('CHROMADB_ENABLED', false),
        'host' => env('CHROMADB_HOST', 'localhost'),
        'port' => env('CHROMADB_PORT', 8000),
        'embedding_server' => env('CHROMADB_EMBEDDING_SERVER', 'http://localhost:8001'),
        'model' => env('CHROMADB_EMBEDDING_MODEL', 'all-MiniLM-L6-v2'),
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
];
