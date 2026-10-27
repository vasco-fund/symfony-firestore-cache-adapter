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
     * Atomically create a document, failing if a document with the same ID already exists.
     *
     * Atomicity is guaranteed by Firestore itself: the underlying HTTP implementation uses the
     * `POST .../documents/{collection}?documentId={id}` endpoint, whose semantics is "create-only" —
     * Firestore returns ALREADY_EXISTS (HTTP 409) if the document is already present, with no race
     * window between the existence check and the write. This is the same primitive as the SDK's
     * `DocumentReference::create()` (vs `set()` which upserts) and is functionally equivalent to a
     * `PATCH` with the `currentDocument.exists=false` precondition.
     *
     * Returns true if the document was created, false if a document with this ID already exists.
     * Any other transport-level error is surfaced through the underlying HTTP client exception.
     *
     * @param array<string, mixed> $data
     */
    public function createDocument(string $collection, string $documentId, array $data): bool;

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
    public function createBatch(): BatchInterface;

    /**
     * Delete all documents in a collection (in batches).
     */
    public function deleteCollection(string $collection, int $batchSize): void;
}
