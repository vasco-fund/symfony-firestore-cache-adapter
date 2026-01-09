<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client\Http;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vasco\FirestoreCache\Client\BatchInterface;

/**
 * HTTP-based implementation of BatchInterface.
 *
 * Note: Firestore REST API doesn't support native batching like the SDK.
 * This implementation executes operations sequentially on commit.
 */
final class HttpBatch implements BatchInterface
{
    /** @var array<array{type: string, collection: string, documentId: string, data?: array<string, mixed>}> */
    private array $operations = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $projectId,
        private readonly string $database,
        private readonly string $accessToken,
    ) {
    }

    public function set(string $collection, string $documentId, array $data): self
    {
        $this->operations[] = [
            'type' => 'set',
            'collection' => $collection,
            'documentId' => $documentId,
            'data' => $data,
        ];

        return $this;
    }

    public function delete(string $collection, string $documentId): self
    {
        $this->operations[] = [
            'type' => 'delete',
            'collection' => $collection,
            'documentId' => $documentId,
        ];

        return $this;
    }

    public function commit(): void
    {
        foreach ($this->operations as $operation) {
            if ($operation['type'] === 'set') {
                $this->executeSet(
                    $operation['collection'],
                    $operation['documentId'],
                    $operation['data'] ?? []
                );
            } elseif ($operation['type'] === 'delete') {
                $this->executeDelete($operation['collection'], $operation['documentId']);
            }
        }

        $this->operations = [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function executeSet(string $collection, string $documentId, array $data): void
    {
        $url = \sprintf(
            FirestoreHttpClient::FIRESTORE_BASE_URL . '/documents/%s/%s',
            $this->projectId,
            $this->database,
            $collection,
            $documentId
        );

        $payload = [
            'fields' => FieldFormatter::formatFields($data),
        ];

        $this->httpClient->request('PATCH', $url, [
            'headers' => $this->getHeaders(),
            'json' => $payload,
        ]);
    }

    private function executeDelete(string $collection, string $documentId): void
    {
        $url = \sprintf(
            FirestoreHttpClient::FIRESTORE_BASE_URL . '/documents/%s/%s',
            $this->projectId,
            $this->database,
            $collection,
            $documentId
        );

        $this->httpClient->request('DELETE', $url, [
            'headers' => $this->getHeaders(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ];
    }
}
