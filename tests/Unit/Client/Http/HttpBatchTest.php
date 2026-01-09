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
use Vasco\FirestoreCache\Client\Http\HttpBatch;

#[CoversClass(HttpBatch::class)]
#[UsesClass(FieldFormatter::class)]
#[UsesClass(BatchOperation::class)]
final class HttpBatchTest extends TestCase
{
    public function testCommitExecutesQueuedOperations(): void
    {
        $tokenRetriever = $this->createMock(AccessTokenRetriever::class);
        $tokenRetriever->method('getToken')
            ->willReturn('token');

        $httpClient = new RecordingHttpClient();
        $batch = new HttpBatch($httpClient, 'project', '(default)', $tokenRetriever);

        $batch->set('cache', 'doc', ['value' => 10])
            ->delete('cache', 'doc')
            ->commit();

        $batch->commit();

        self::assertCount(2, $httpClient->requests);
        self::assertSame('PATCH', $httpClient->requests[0]['method']);
        self::assertSame(
            'https://firestore.googleapis.com/v1/projects/project/databases/(default)/documents/cache/doc',
            $httpClient->requests[0]['url']
        );
        self::assertSame('Bearer token', $httpClient->requests[0]['options']['headers']['Authorization']);
        self::assertSame(['integerValue' => 10], $httpClient->requests[0]['options']['json']['fields']['value']);

        self::assertSame('DELETE', $httpClient->requests[1]['method']);
        self::assertSame(
            'https://firestore.googleapis.com/v1/projects/project/databases/(default)/documents/cache/doc',
            $httpClient->requests[1]['url']
        );
        self::assertSame('Bearer token', $httpClient->requests[1]['options']['headers']['Authorization']);
    }
}

final class RecordingHttpClient implements HttpClientInterface
{
    /** @var array<int, array{method: string, url: string, options: array}> */
    public array $requests = [];

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'options' => $options,
        ];

        return new class implements ResponseInterface {
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
