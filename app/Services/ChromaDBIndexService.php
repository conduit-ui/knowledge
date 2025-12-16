<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ChromaDBClientInterface;
use App\Contracts\EmbeddingServiceInterface;
use App\Models\Entry;

class ChromaDBIndexService
{
    private string $collectionName = 'knowledge_entries';

    public function __construct(
        private readonly ChromaDBClientInterface $chromaDBClient,
        private readonly EmbeddingServiceInterface $embeddingService
    ) {}

    /**
     * Index an entry in ChromaDB.
     */
    public function indexEntry(Entry $entry): void
    {
        $embedding = $this->getOrGenerateEmbedding($entry);

        if (count($embedding) === 0) {
            return;
        }

        try {
            $collection = $this->chromaDBClient->getOrCreateCollection($this->collectionName);

            $this->chromaDBClient->add(
                $collection['id'],
                [$this->getDocumentId($entry)],
                [$embedding],
                [$this->getMetadata($entry)],
                [$entry->content]
            );
        } catch (\RuntimeException $e) {
            // Gracefully handle indexing failures
            return;
        }
    }

    /**
     * Update an entry in the ChromaDB index.
     */
    public function updateEntry(Entry $entry): void
    {
        $embedding = $this->getOrGenerateEmbedding($entry);

        if (count($embedding) === 0) {
            return;
        }

        try {
            $collection = $this->chromaDBClient->getOrCreateCollection($this->collectionName);

            $this->chromaDBClient->update(
                $collection['id'],
                [$this->getDocumentId($entry)],
                [$embedding],
                [$this->getMetadata($entry)],
                [$entry->content]
            );
        } catch (\RuntimeException $e) {
            // Gracefully handle update failures
            return;
        }
    }

    /**
     * Remove an entry from the ChromaDB index.
     */
    public function removeEntry(Entry $entry): void
    {
        try {
            $collection = $this->chromaDBClient->getOrCreateCollection($this->collectionName);

            $this->chromaDBClient->delete(
                $collection['id'],
                [$this->getDocumentId($entry)]
            );
        } catch (\RuntimeException $e) {
            // Gracefully handle deletion failures
            return;
        }
    }

    /**
     * Index multiple entries in bulk.
     *
     * @param  iterable<Entry>  $entries
     */
    public function indexBatch(iterable $entries): void
    {
        $ids = [];
        $embeddings = [];
        $metadatas = [];
        $documents = [];

        foreach ($entries as $entry) {
            $embedding = $this->getOrGenerateEmbedding($entry);

            if (count($embedding) === 0) {
                continue;
            }

            $ids[] = $this->getDocumentId($entry);
            $embeddings[] = $embedding;
            $metadatas[] = $this->getMetadata($entry);
            $documents[] = $entry->content;
        }

        if (count($ids) === 0) {
            return;
        }

        try {
            $collection = $this->chromaDBClient->getOrCreateCollection($this->collectionName);

            $this->chromaDBClient->add(
                $collection['id'],
                $ids,
                $embeddings,
                $metadatas,
                $documents
            );
        } catch (\RuntimeException $e) {
            // Gracefully handle batch indexing failures
            return;
        }
    }

    /**
     * Get or generate embedding for an entry.
     *
     * @return array<int, float>
     */
    private function getOrGenerateEmbedding(Entry $entry): array
    {
        // Check if embedding exists in database
        if ($entry->embedding !== null) {
            $embedding = json_decode($entry->embedding, true);
            if (is_array($embedding) && count($embedding) > 0) {
                return $embedding;
            }
        }

        // Generate new embedding
        $text = $entry->title.' '.$entry->content;
        $embedding = $this->embeddingService->generate($text);

        // Store embedding in database for future use
        if (count($embedding) > 0) {
            $jsonEncoded = json_encode($embedding);
            if ($jsonEncoded !== false) {
                $entry->embedding = $jsonEncoded;
                $entry->save();
            }
        }

        return $embedding;
    }

    /**
     * Get ChromaDB document ID for an entry.
     */
    private function getDocumentId(Entry $entry): string
    {
        return 'entry_'.$entry->id;
    }

    /**
     * Get metadata for an entry.
     *
     * @return array<string, mixed>
     */
    private function getMetadata(Entry $entry): array
    {
        return [
            'entry_id' => $entry->id,
            'title' => $entry->title,
            'category' => $entry->category ?? '',
            'module' => $entry->module ?? '',
            'priority' => $entry->priority,
            'status' => $entry->status,
            'confidence' => $entry->confidence,
        ];
    }
}
