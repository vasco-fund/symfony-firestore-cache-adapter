<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client\Http;

readonly class BatchOperation
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $type,
        public string $collection,
        public string $documentId,
        public ?array $data = null,
    ) {
    }
}
