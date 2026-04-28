<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client\Http;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vasco\FirestoreCache\Client\BatchInterface;
use Vasco\FirestoreCache\Client\DocumentSnapshotInterface;
use Vasco\FirestoreCache\Client\FirestoreClientInterface;

/**
 * HTTP-based Firestore client using Symfony HttpClient.
 *
 * This implementation uses the Firestore REST API and doesn't require
 * the grpc extension, making it easier to deploy in various environments.
 */
final readonly class FirestoreHttpClient implements FirestoreClientInterface
{
    public const FIRESTORE_BASE_URL = 'https://firestore.googleapis.com/v1/projects/%s/databases/%s';
    private HttpClientInterface $httpClient;
    private AccessTokenRetriever $accessTokenRetriever;

    public function __construct(
        private string $projectId,
        private string $database = '(default)',
        ?HttpClientInterface $httpClient = null,
        ?AccessTokenRetriever $accessTokenRetriever = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
        $this->accessTokenRetriever = $accessTokenRetriever ?? new AccessTokenRetriever();
    }

    public function getDocument(string $collection, string $documentId): DocumentSnapshotInterface
    {
        $url = $this->documentUrl($collection, $documentId);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $this->headers(),
            ]);

            return new LazyHttpDocumentSnapshot($documentId, $response);
        } catch (HttpExceptionInterface $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return HttpDocumentSnapshot::fromEmptyResponse($documentId);
            }
            throw $e;
        }
    }

    public function setDocument(string $collection, string $documentId, array $data): void
    {
        $url = $this->documentUrl($collection, $documentId);

        $payload = [
            'fields' => FieldFormatter::formatFields($data),
        ];

        $this->httpClient->request('PATCH', $url, [
            'headers' => $this->headers(),
            'json' => $payload,
        ]);
    }

    /**
     * Atomically create a document, failing if a document with the same ID already exists.
     *
     * Uses Firestore's dedicated create endpoint:
     *
     *     POST /v1/projects/{p}/databases/{db}/documents/{collection}?documentId={id}
     *
     * This endpoint is intrinsically "create-only" — there is no race window between an existence
     * check and the write. If a document with this ID already exists in the collection, Firestore
     * returns ALREADY_EXISTS (HTTP 409) and this method returns false. This is the REST equivalent
     * of `DocumentReference::create()` from the SDK, and is functionally equivalent to a
     * `PATCH .../documents/{collection}/{id}?currentDocument.exists=false`.
     *
     * Returns true if the document was created, false on 409 (already exists). Any other 4xx/5xx
     * status is surfaced as the underlying Symfony HttpClient exception.
     */
    public function createDocument(string $collection, string $documentId, array $data): bool
    {
        $url = \sprintf(
            self::FIRESTORE_BASE_URL . '/documents/%s',
            $this->projectId,
            $this->database,
            $collection,
        );

        $payload = [
            'fields' => FieldFormatter::formatFields($data),
        ];

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $this->headers(),
            'query' => ['documentId' => $documentId],
            'json' => $payload,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode === 409) {
            return false;
        }
        if ($statusCode >= 400) {
            // Surface the underlying Symfony HttpClient exception for any other error.
            $response->getContent();
        }

        return true;
    }

    public function deleteDocument(string $collection, string $documentId): void
    {
        $url = $this->documentUrl($collection, $documentId);

        $this->httpClient->request('DELETE', $url, [
            'headers' => $this->headers(),
        ]);
    }

    public function queryDocuments(
        string $collection,
        string $field,
        string $operator,
        mixed $value,
        ?int $limit = null,
    ): iterable {
        $url = \sprintf(
            self::FIRESTORE_BASE_URL . '/documents:runQuery',
            $this->projectId,
            $this->database
        );

        $query = [
            'from' => [['collectionId' => $collection]],
        ];

        // Add where clause
        $filter = [
            'fieldFilter' => [
                'field' => ['fieldPath' => $field],
                'op' => $this->mapOperator($operator),
                'value' => FieldFormatter::formatValue($value),
            ],
        ];

        $query['where'] = $filter;

        if ($limit !== null) {
            $query['limit'] = $limit;
        }

        $payload = ['structuredQuery' => $query];

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $this->headers(),
            'json' => $payload,
        ]);

        $results = $response->toArray(false);

        foreach ($results as $result) {
            if (isset($result['document']) && \is_array($result['document'])) {
                $doc = $result['document'];
                $name = $doc['name'] ?? '';
                if (!\is_string($name)) {
                    continue;
                }
                $parts = explode('/', $name);
                $docId = end($parts);
                /**
                 * end() can technically return false but won't in our usage after string checks.
                 *
                 * @phpstan-ignore identical.alwaysFalse
                 */
                if ($docId === false) {
                    continue;
                }

                yield HttpDocumentSnapshot::fromApiResponse($docId, $doc);
            }
        }
    }

    public function createBatch(): BatchInterface
    {
        return new HttpBatch(
            $this->httpClient,
            $this->projectId,
            $this->database,
            $this->accessTokenRetriever
        );
    }

    public function deleteCollection(string $collection, int $batchSize): void
    {
        $url = \sprintf(
            self::FIRESTORE_BASE_URL . '/documents/%s',
            $this->projectId,
            $this->database,
            $collection
        );

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $this->headers(),
                'query' => ['pageSize' => $batchSize],
            ]);

            $data = $response->toArray(false);
            $documents = $data['documents'] ?? [];

            if (!\is_array($documents) || $documents === []) {
                return;
            }

            $batch = $this->createBatch();
            foreach ($documents as $document) {
                if (!\is_array($document)) {
                    continue;
                }
                $name = $document['name'] ?? '';
                if (!\is_string($name)) {
                    continue;
                }
                $parts = explode('/', $name);
                $docId = end($parts);
                /**
                 * end() can technically return false but won't in our usage after string checks.
                 *
                 * @phpstan-ignore identical.alwaysFalse
                 */
                if ($docId === false) {
                    continue;
                }

                $batch->delete($collection, $docId);
            }

            $batch->commit();

            // Recursively delete remaining documents
            $this->deleteCollection($collection, $batchSize);
        } catch (HttpExceptionInterface $e) {
            // Collection might be empty or not exist, which is fine
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        }
    }

    /**
     * Get the full URL for a document.
     */
    private function documentUrl(string $collection, string $documentId): string
    {
        return \sprintf(
            self::FIRESTORE_BASE_URL . '/documents/%s/%s',
            $this->projectId,
            $this->database,
            $collection,
            $documentId
        );
    }

    /**
     * Map operator to Firestore operator format.
     */
    private function mapOperator(string $op): string
    {
        return match ($op) {
            '=', '==' => 'EQUAL',
            '!=' => 'NOT_EQUAL',
            '>' => 'GREATER_THAN',
            '>=' => 'GREATER_THAN_OR_EQUAL',
            '<' => 'LESS_THAN',
            '<=' => 'LESS_THAN_OR_EQUAL',
            'array-contains' => 'ARRAY_CONTAINS',
            'in' => 'IN',
            'array-contains-any' => 'ARRAY_CONTAINS_ANY',
            default => throw new \InvalidArgumentException("Unsupported operator: $op"),
        };
    }

    /**
     * Get HTTP headers for requests.
     *
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessTokenRetriever->getToken(),
            'Content-Type' => 'application/json',
        ];
    }
}
