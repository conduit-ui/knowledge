<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Embedding Provider
    |--------------------------------------------------------------------------
    |
    | The embedding provider to use. Set to "none" in tests.
    |
    */

    'embedding_provider' => env('EMBEDDING_PROVIDER', 'qdrant'),

    /*
    |--------------------------------------------------------------------------
    | Qdrant Vector Database
    |--------------------------------------------------------------------------
    */

    'qdrant' => [
        'enabled' => env('QDRANT_ENABLED', true),
        'host' => env('QDRANT_HOST', 'localhost'),
        'port' => env('QDRANT_PORT', 6333),
        'secure' => env('QDRANT_SECURE', false),
        'api_key' => env('QDRANT_API_KEY'),
        'embedding_server' => env('QDRANT_EMBEDDING_SERVER', 'http://localhost:8001'),
        'collection' => env('QDRANT_COLLECTION', 'knowledge'),
        'cache_embeddings' => env('QDRANT_CACHE_EMBEDDINGS', true),
        'cache_ttl' => env('QDRANT_CACHE_TTL', 604800), // 7 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Dimension
    |--------------------------------------------------------------------------
    |
    | bge-large-en-v1.5: 1024
    | all-MiniLM-L6-v2: 384
    |
    */

    'embedding_dimension' => env('EMBEDDING_DIMENSION', 1024),

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    */

    'minimum_similarity' => env('SEARCH_MIN_SIMILARITY', 0.3),
    'max_results' => env('SEARCH_MAX_RESULTS', 20),

    /*
    |--------------------------------------------------------------------------
    | Hybrid Search Configuration
    |--------------------------------------------------------------------------
    |
    | Hybrid search combines dense (semantic) and sparse (BM25/lexical) vectors
    | using Reciprocal Rank Fusion (RRF) for improved relevance.
    |
    | When enabled, the collection will store both dense and sparse vectors,
    | and the hybridSearch() method can be used for combined retrieval.
    |
    */

    'hybrid' => [
        'enabled' => env('HYBRID_SEARCH_ENABLED', false),
        'prefetch_limit' => env('HYBRID_PREFETCH_LIMIT', 40),
        'sparse_vocab_size' => env('HYBRID_SPARSE_VOCAB_SIZE', 30000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ollama Configuration
    |--------------------------------------------------------------------------
    */

    'ollama' => [
        'enabled' => env('OLLAMA_ENABLED', true),
        'host' => env('OLLAMA_HOST', 'localhost'),
        'port' => env('OLLAMA_PORT', 11434),
        'model' => env('OLLAMA_MODEL', 'llama3.2:3b'),
        'timeout' => env('OLLAMA_TIMEOUT', 30),
        'auto_tag' => env('OLLAMA_AUTO_TAG', true),
        'auto_categorize' => env('OLLAMA_AUTO_CATEGORIZE', true),
        'extract_concepts' => env('OLLAMA_EXTRACT_CONCEPTS', true),
        'suggest_relationships' => env('OLLAMA_SUGGEST_RELATIONSHIPS', true),
        'enhance_queries' => env('OLLAMA_ENHANCE_QUERIES', true),
    ],
];
