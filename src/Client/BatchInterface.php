<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client;

/**
 * Interface for Firestore batch operations.
 */
interface BatchInterface
{
    /**
     * Add a set operation to the batch.
     *
     * @param array<string, mixed> $data
     */
    public function set(string $collection, string $documentId, array $data): self;

    /**
     * Add a delete operation to the batch.
     */
    public function delete(string $collection, string $documentId): self;

    /**
     * Commit all operations in the batch.
     */
    public function commit(): void;
}
