<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client;

/**
 * Interface for a Firestore document snapshot.
 */
interface DocumentSnapshotInterface
{
    /**
     * Check if the document exists.
     */
    public function exists(): bool;

    /**
     * Get the document data.
     *
     * @return array<string, mixed>|null
     */
    public function data(): ?array;

    /**
     * Get the document ID.
     */
    public function id(): string;
}
