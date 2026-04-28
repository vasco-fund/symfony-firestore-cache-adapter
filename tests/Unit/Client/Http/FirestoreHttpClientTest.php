<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Tests\Unit\Client\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Vasco\FirestoreCache\Client\Http\AccessTokenRetriever;
use Vasco\FirestoreCache\Client\Http\BatchOperation;
use Vasco\FirestoreCache\Client\Http\FieldFormatter;
use Vasco\FirestoreCache\Client\Http\FirestoreHttpClient;
use Vasco\FirestoreCache\Client\Http\HttpBatch;
use Vasco\FirestoreCache\Client\Http\HttpDocumentSnapshot;
use Vasco\FirestoreCache\Client\Http\LazyHttpDocumentSnapshot;

#[CoversClass(FirestoreHttpClient::class)]
#[UsesClass(FieldFormatter::class)]
#[UsesClass(HttpDocumentSnapshot::class)]
#[UsesClass(LazyHttpDocumentSnapshot::class)]
#[UsesClass(HttpBatch::class)]
#[UsesClass(BatchOperation::class)]
final class FirestoreHttpClientTest extends TestCase
{
    public function testGetDocumentReturnsSnapshot(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://firestore.googleapis.com/v1/projects/project/databases/(default)/documents/cache/doc',
                $this->callback(function (array $options): bool {
                    return $options['headers']['Authorization'] === 'Bearer token';
                })
            )
            ->willReturn($response);

        $client = $this->createClient($httpClient);

        $snapshot = $client->getDocument('cache', 'doc');

        self::assertSame('doc', $snapshot->getId());
        self::assertTrue($snapshot->exists());
    }

    public function testSetDocumentUsesFormattedPayload(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'PATCH',
                'https://firestore.googleapis.com/v1/projects/project/databases/(default)/documents/cache/doc',
                $this->callback(function (array $options): bool {
                    return $options['headers']['Authorization'] === 'Bearer token'
                        && $options['json']['fields']['count'] === ['integerValue' => 3];
                })
            )
            ->willReturn($response);

        $client = $this->createClient($httpClient);

        $client->setDocument('cache', 'doc', ['count' => 3]);
    }

    public function testCreateDocumentReturnsTrueOnSuccess(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->never())
            ->method('getContent');

        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://firestore.googleapis.com/v1/projects/project/databases/(default)/documents/cache',
                $this->callback(function (array $options): bool {
                    return $options['headers']['Authorization'] === 'Bearer token'
                        && $options['query'] === ['documentId' => 'doc']
                        && $options['json']['fields']['count'] === ['integerValue' => 3];
                })
            )
            ->willReturn($response);

        $client = $this->createClient($httpClient);

        self::assertTrue($client->createDocument('cache', 'doc', ['count' => 3]));
    }

    public function testCreateDocumentReturnsFalseOnConflict(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(409);
        $response->expects($this->never())
            ->method('getContent');

        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $client = $this->createClient($httpClient);

        self::assertFalse($client->createDocument('cache', 'doc', ['count' => 3]));
    }

    public function testCreateDocumentSurfacesOtherHttpErrors(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(500);
        $response->expects($this->once())
            ->method('getContent')
            ->willThrowException(new \RuntimeException('boom'));

        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $client = $this->createClient($httpClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $client->createDocument('cache', 'doc', ['count' => 3]);
    }

    public function testDeleteDocumentIssuesDeleteRequest(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'DELETE',
                'https://firestore.googleapis.com/v1/projects/project/databases/(default)/documents/cache/doc',
                $this->callback(function (array $options): bool {
                    return $options['headers']['Authorization'] === 'Bearer token';
                })
            )
            ->willReturn($response);

        $client = $this->createClient($httpClient);

        $client->deleteDocument('cache', 'doc');
    }

    public function testDeleteCollectionNoDocumentsReturnsEarly(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://firestore.googleapis.com/v1/projects/project/databases/(default)/documents/cache',
                $this->callback(function (array $options): bool {
                    return $options['headers']['Authorization'] === 'Bearer token'
                        && $options['query'] === ['pageSize' => 10];
                })
            )
            ->willReturn($response);

        $response->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn(['documents' => []]);

        $client = $this->createClient($httpClient);

        $client->deleteCollection('cache', 10);
    }

    public function testBatchReturnsHttpBatch(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $client = $this->createClientWithCalls($httpClient, 0);

        $batch = $client->createBatch();

        self::assertInstanceOf(HttpBatch::class, $batch);
    }

    public function testQueryDocumentsParsesSnapshots(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://firestore.googleapis.com/v1/projects/project/databases/(default)/documents:runQuery',
                $this->callback(function (array $options): bool {
                    return $options['headers']['Authorization'] === 'Bearer token'
                        && $options['json']['structuredQuery']['from'][0]['collectionId'] === 'cache'
                        && $options['json']['structuredQuery']['where']['fieldFilter']['field']['fieldPath'] === 'key'
                        && $options['json']['structuredQuery']['where']['fieldFilter']['op'] === 'EQUAL'
                        && !isset($options['json']['structuredQuery']['limit']);
                })
            )
            ->willReturn($response);

        $response->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn([
                ['document' => ['name' => 123]],
                [
                    'document' => [
                        'name' => 'projects/project/databases/(default)/documents/cache/doc1',
                        'fields' => [
                            'count' => ['integerValue' => 2],
                        ],
                    ],
                ],
                ['no_document' => []],
            ]);

        $client = $this->createClient($httpClient);

        $results = iterator_to_array($client->queryDocuments('cache', 'key', '=', 'value'));

        self::assertCount(1, $results);
        self::assertInstanceOf(HttpDocumentSnapshot::class, $results[0]);
        self::assertSame(['count' => 2], $results[0]->getData());
    }

    public function testQueryDocumentsAddsLimitWhenProvided(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://firestore.googleapis.com/v1/projects/project/databases/(default)/documents:runQuery',
                $this->callback(function (array $options): bool {
                    return $options['json']['structuredQuery']['limit'] === 5;
                })
            )
            ->willReturn($response);

        $response->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn([]);

        $client = $this->createClient($httpClient);

        iterator_to_array($client->queryDocuments('cache', 'key', '=', 'value', 5));
    }

    public function testDeleteCollectionDeletesDocumentsInBatches(): void
    {
        $httpClient = new QueueHttpClient([
            $this->createResponse([
                'documents' => [
                    ['name' => 'projects/project/databases/(default)/documents/cache/doc1'],
                    ['name' => 'projects/project/databases/(default)/documents/cache/doc2'],
                ],
            ]),
            $this->createResponse([]),
            $this->createResponse([]),
            $this->createResponse(['documents' => []]),
        ]);

        $client = $this->createClientWithCalls($httpClient, 4);

        $client->deleteCollection('cache', 2);

        self::assertSame('GET', $httpClient->requests[0]['method']);
        self::assertSame('DELETE', $httpClient->requests[1]['method']);
        self::assertSame('DELETE', $httpClient->requests[2]['method']);
        self::assertSame('GET', $httpClient->requests[3]['method']);
    }

    public function testMapOperatorAndDocumentUrlHelpers(): void
    {
        $client = new FirestoreHttpClient('project', '(default)', $this->createMock(HttpClientInterface::class));

        $mapOperator = new \ReflectionMethod(FirestoreHttpClient::class, 'mapOperator');
        $mapOperator->setAccessible(true);

        self::assertSame('EQUAL', $mapOperator->invoke($client, '='));
        self::assertSame('EQUAL', $mapOperator->invoke($client, '=='));
        self::assertSame('NOT_EQUAL', $mapOperator->invoke($client, '!='));
        self::assertSame('GREATER_THAN', $mapOperator->invoke($client, '>'));
        self::assertSame('GREATER_THAN_OR_EQUAL', $mapOperator->invoke($client, '>='));
        self::assertSame('LESS_THAN', $mapOperator->invoke($client, '<'));
        self::assertSame('LESS_THAN_OR_EQUAL', $mapOperator->invoke($client, '<='));
        self::assertSame('ARRAY_CONTAINS', $mapOperator->invoke($client, 'array-contains'));
        self::assertSame('IN', $mapOperator->invoke($client, 'in'));
        self::assertSame('ARRAY_CONTAINS_ANY', $mapOperator->invoke($client, 'array-contains-any'));

        $this->expectException(\InvalidArgumentException::class);
        $mapOperator->invoke($client, 'unsupported');
    }

    public function testDocumentUrlHelper(): void
    {
        $client = new FirestoreHttpClient('project', '(default)', $this->createMock(HttpClientInterface::class));

        $documentUrl = new \ReflectionMethod(FirestoreHttpClient::class, 'documentUrl');
        $documentUrl->setAccessible(true);

        self::assertSame(
            'https://firestore.googleapis.com/v1/projects/project/databases/(default)/documents/cache/doc',
            $documentUrl->invoke($client, 'cache', 'doc')
        );
    }

    private function createClient(HttpClientInterface $httpClient): FirestoreHttpClient
    {
        return $this->createClientWithCalls($httpClient, 1);
    }

    private function createClientWithCalls(HttpClientInterface $httpClient, int $tokenCalls): FirestoreHttpClient
    {
        $tokenRetriever = $this->createMock(AccessTokenRetriever::class);
        $tokenRetriever->expects($this->exactly($tokenCalls))
            ->method('getToken')
            ->willReturn('token');

        return new FirestoreHttpClient('project', '(default)', $httpClient, $tokenRetriever);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createResponse(array $data): ResponseInterface
    {
        return new class($data) implements ResponseInterface {
            public function __construct(private array $data)
            {
            }

            public function getStatusCode(): int
            {
                return 200;
            }

            public function getHeaders(bool $throw = true): array
            {
                return [];
            }

            public function getContent(bool $throw = true): string
            {
                return '';
            }

            public function toArray(bool $throw = true): array
            {
                return $this->data;
            }

            public function cancel(): void
            {
            }

            public function getInfo(?string $type = null): mixed
            {
                return null;
            }
        };
    }
}

final class QueueHttpClient implements HttpClientInterface
{
    /** @var array<int, ResponseInterface> */
    private array $responses;

    /** @var array<int, array{method: string, url: string, options: array}> */
    public array $requests = [];

    /**
     * @param array<int, ResponseInterface> $responses
     */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'options' => $options,
        ];

        return array_shift($this->responses) ?? new class implements ResponseInterface {
            public function getStatusCode(): int
            {
                return 200;
            }

            public function getHeaders(bool $throw = true): array
            {
                return [];
            }

            public function getContent(bool $throw = true): string
            {
                return '';
            }

            public function toArray(bool $throw = true): array
            {
                return [];
            }

            public function cancel(): void
            {
            }

            public function getInfo(?string $type = null): mixed
            {
                return null;
            }
        };
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): \Symfony\Contracts\HttpClient\ResponseStreamInterface
    {
        throw new \BadMethodCallException('Not implemented for tests.');
    }

    public function withOptions(array $options): static
    {
        return $this;
    }
}
