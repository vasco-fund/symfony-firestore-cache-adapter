<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client;

/**
 * Interface for Firestore client implementations.
 *
 * This abstraction allows using either the native Google Cloud SDK
 * or a custom HTTP-based client.
 */
interface FirestoreClientInterface
{
    /**
     * Get a document snapshot by its ID.
     */
    public function getDocument(string $collection, string $documentId): DocumentSnapshotInterface;

    /**
     * Set (create or update) a document.
     *
     * @param array<string, mixed> $data
     */
    public function setDocument(string $collection, string $documentId, array $data): void;

    /**
     * Delete a document.
     */
    public function deleteDocument(string $collection, string $documentId): void;

    /**
     * Query documents with a where clause.
     *
     * @return iterable<DocumentSnapshotInterface>
     */
    public function queryDocuments(
        string $collection,
        string $field,
        string $operator,
        mixed $value,
        ?int $limit = null,
    ): iterable;

    /**
     * Create a new batch operation.
     */
    public function batch(): BatchInterface;

    /**
     * Delete all documents in a collection (in batches).
     */
    public function deleteCollection(string $collection, int $batchSize): void;
}
