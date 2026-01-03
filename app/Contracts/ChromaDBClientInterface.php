<?php

declare(strict_types=1);

namespace App\Contracts;

interface ChromaDBClientInterface
{
    /**
     * Create or get a collection in ChromaDB.
     *
     * @param  string  $name  Collection name
     * @return array<string, mixed> Collection metadata
     */
    public function getOrCreateCollection(string $name): array;

    /**
     * Add documents to a collection.
     *
     * @param  string  $collectionId  Collection ID
     * @param  array<int, string>  $ids  Document IDs
     * @param  array<int, array<int, float>>  $embeddings  Document embeddings
     * @param  array<int, array<string, mixed>>  $metadatas  Document metadata
     * @param  array<int, string>|null  $documents  Optional document texts
     */
    public function add(
        string $collectionId,
        array $ids,
        array $embeddings,
        array $metadatas,
        ?array $documents = null
    ): void;

    /**
     * Query a collection for similar documents.
     *
     * @param  string  $collectionId  Collection ID
     * @param  array<int, float>  $queryEmbedding  Query embedding
     * @param  int  $nResults  Number of results to return
     * @param  array<string, mixed>  $where  Optional metadata filters
     * @return array<string, mixed> Query results
     */
    public function query(
        string $collectionId,
        array $queryEmbedding,
        int $nResults = 10,
        array $where = []
    ): array;

    /**
     * Delete documents from a collection.
     *
     * @param  string  $collectionId  Collection ID
     * @param  array<int, string>  $ids  Document IDs to delete
     */
    public function delete(string $collectionId, array $ids): void;

    /**
     * Update documents in a collection.
     *
     * @param  string  $collectionId  Collection ID
     * @param  array<int, string>  $ids  Document IDs
     * @param  array<int, array<int, float>>  $embeddings  Document embeddings
     * @param  array<int, array<string, mixed>>  $metadatas  Document metadata
     * @param  array<int, string>|null  $documents  Optional document texts
     */
    public function update(
        string $collectionId,
        array $ids,
        array $embeddings,
        array $metadatas,
        ?array $documents = null
    ): void;

    /**
     * Check if ChromaDB is available.
     */
    public function isAvailable(): bool;

    /**
     * Get all document IDs and metadata from a collection.
     *
     * @param  string  $collectionId  Collection ID
     * @param  int  $limit  Maximum number of documents to retrieve
     * @return array{ids: array<string>, metadatas: array<array<string, mixed>>}
     */
    public function getAll(string $collectionId, int $limit = 10000): array;
}
