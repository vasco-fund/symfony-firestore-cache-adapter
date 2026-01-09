<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client\Http;

use Vasco\FirestoreCache\Client\DocumentSnapshotInterface;

/**
 * Content based implementation of DocumentSnapshotInterface.
 */
final class HttpDocumentSnapshot implements DocumentSnapshotInterface
{
    use HttpDocumentTrait;

    /**
     * @param mixed[] $data
     */
    private function __construct(
        private string $id,
        private bool $exists = true,
        private ?array $data = [],
    ) {
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Build a snapshot from a Firestore REST API document payload.
     *
     * @param array<string, mixed> $document
     */
    public static function fromApiResponse(string $id, array $document): self
    {
        $fields = $document['fields'] ?? [];
        if (!\is_array($fields)) {
            $data = null;
        } else {
            $data = self::parseFields($fields);
        }

        return new self($id, true, $data);
    }

    public static function fromEmptyResponse(string $id): self
    {
        return new self($id, false, null);
    }
}
