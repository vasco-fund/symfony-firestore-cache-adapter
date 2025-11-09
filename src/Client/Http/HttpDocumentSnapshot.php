<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client\Http;

use Symfony\Contracts\HttpClient\ResponseInterface;
use Vasco\FirestoreCache\Client\DocumentSnapshotInterface;

/**
 * HTTP-based implementation of DocumentSnapshotInterface.
 */
final class HttpDocumentSnapshot implements DocumentSnapshotInterface
{
    private bool $responseParsed = false;

    /**
     * @var mixed[]|null
     */
    private ?array $data;

    public function __construct(
        private string $id,
        private ResponseInterface $response,
    ) {
    }

    public function exists(): bool
    {
        return $this->response->getStatusCode() !== 404;
    }

    public function data(): ?array
    {
        if ($this->responseParsed) {
            return $this->data;
        }

        $content = $this->response->toArray();

        if ($content === []) {
            $this->data = null;

            return null;
        }

        $fields = $content['fields'] ?? [];
        if (!\is_array($fields)) {
            $this->data = null;

            return null;
        }

        $this->data = self::parseFields($fields);

        return $this->data;
    }

    public function id(): string
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
        $response = new class($document) implements ResponseInterface {
            /**
             * @param array<string, mixed> $document
             */
            public function __construct(private array $document)
            {
            }

            public function getStatusCode(): int
            {
                return 200;
            }

            /**
             * @return array{fields: mixed}
             */
            public function toArray(bool $throw = true): array
            {
                return ['fields' => $this->document['fields'] ?? []];
            }

            public function getHeaders(bool $throw = true): array
            {
                return [];
            }

            public function getContent(bool $throw = true): string
            {
                return '';
            }

            public function cancel(): void
            {
            }

            public function getInfo(?string $type = null): mixed
            {
                $info = [
                    'canceled' => false,
                    'error' => null,
                    'http_code' => 200,
                    'http_method' => 'GET',
                    'redirect_count' => 0,
                    'redirect_url' => null,
                    'response_headers' => [],
                    'start_time' => 0.0,
                    'url' => '',
                    'user_data' => null,
                ];

                return $type === null ? $info : ($info[$type] ?? null);
            }
        };

        return new self($id, $response);
    }

    /**
     * Parse Firestore field format to PHP values.
     *
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private static function parseFields(array $fields): array
    {
        $data = [];

        foreach ($fields as $key => $field) {
            $data[$key] = self::parseFieldValue($field);
        }

        return $data;
    }

    /**
     * Parse a single Firestore field value.
     */
    private static function parseFieldValue(mixed $field): mixed
    {
        if (!\is_array($field)) {
            return null;
        }

        // Handle different Firestore value types
        if (isset($field['stringValue'])) {
            return $field['stringValue'];
        }

        if (isset($field['bytesValue'])) {
            // Decode base64-encoded binary data
            $decoded = base64_decode($field['bytesValue'], true);

            return $decoded !== false ? $decoded : $field['bytesValue'];
        }

        if (isset($field['integerValue'])) {
            return (int) $field['integerValue'];
        }

        if (isset($field['doubleValue'])) {
            return (float) $field['doubleValue'];
        }

        if (isset($field['booleanValue'])) {
            return (bool) $field['booleanValue'];
        }

        if (isset($field['timestampValue'])) {
            return $field['timestampValue'];
        }

        if (isset($field['arrayValue']['values'])) {
            $values = [];
            foreach ($field['arrayValue']['values'] as $value) {
                $values[] = self::parseFieldValue($value);
            }

            return $values;
        }

        if (isset($field['mapValue']['fields'])) {
            return self::parseFields($field['mapValue']['fields']);
        }

        if (isset($field['nullValue'])) {
            return null;
        }

        return null;
    }
}
