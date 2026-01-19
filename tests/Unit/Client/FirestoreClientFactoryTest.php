<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Tests\Unit\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vasco\FirestoreCache\Client\FirestoreClientFactory;
use Vasco\FirestoreCache\Client\Http\AccessTokenRetriever;
use Vasco\FirestoreCache\Client\Http\FirestoreHttpClient;

#[CoversClass(FirestoreClientFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(FirestoreHttpClient::class)]
final class FirestoreClientFactoryTest extends TestCase
{
    public function testCreateReturnsHttpClientByDefault(): void
    {
        $retriever = $this->createMock(AccessTokenRetriever::class);
        $retriever->expects($this->never())->method('getToken');

        $factory = new FirestoreClientFactory($retriever);

        $client = $factory->create([
            'projectId' => 'project',
            'accessToken' => 'token',
        ]);

        self::assertInstanceOf(FirestoreHttpClient::class, $client);
    }

    public function testCreateRejectsInvalidUseSdk(): void
    {
        $factory = new FirestoreClientFactory($this->createMock(AccessTokenRetriever::class));

        $this->expectException(\InvalidArgumentException::class);

        $factory->create([
            'use_sdk' => 'nope',
            'projectId' => 'project',
            'accessToken' => 'token',
        ]);
    }

    public function testCreateThrowsWhenUseSdkEnabled(): void
    {
        $factory = new FirestoreClientFactory($this->createMock(AccessTokenRetriever::class));

        $this->expectException(\OutOfBoundsException::class);

        $factory->create([
            'use_sdk' => true,
            'projectId' => 'project',
            'accessToken' => 'token',
        ]);
    }

    public function testCreateHttpClientValidatesOptions(): void
    {
        $factory = new FirestoreClientFactory($this->createMock(AccessTokenRetriever::class));

        $this->expectException(\InvalidArgumentException::class);
        $factory->createHttpClient(['projectId' => 123]);
    }

    public function testCreateHttpClientValidatesDatabaseType(): void
    {
        $factory = new FirestoreClientFactory($this->createMock(AccessTokenRetriever::class));

        $this->expectException(\InvalidArgumentException::class);
        $factory->createHttpClient([
            'projectId' => 'project',
            'accessToken' => 'token',
            'database' => 123,
        ]);
    }

    public function testCreateHttpClientValidatesHttpClientType(): void
    {
        $factory = new FirestoreClientFactory($this->createMock(AccessTokenRetriever::class));

        $this->expectException(\InvalidArgumentException::class);
        $factory->createHttpClient([
            'projectId' => 'project',
            'accessToken' => 'token',
            'httpClient' => new \stdClass(),
        ]);
    }

    public function testCreateHttpClientWithCustomHttpClient(): void
    {
        $factory = new FirestoreClientFactory($this->createMock(AccessTokenRetriever::class));
        $httpClient = $this->createMock(HttpClientInterface::class);

        $client = $factory->createHttpClient([
            'projectId' => 'project',
            'accessToken' => 'token',
            'httpClient' => $httpClient,
            'database' => '(default)',
        ]);

        self::assertInstanceOf(FirestoreHttpClient::class, $client);
    }

    public function testCreateHttpClientRejectsInvalidAccessToken(): void
    {
        $factory = new FirestoreClientFactory($this->createMock(AccessTokenRetriever::class));

        $this->expectException(\InvalidArgumentException::class);
        $factory->createHttpClient([
            'projectId' => 'project',
            'accessToken' => 123,
        ]);
    }
}
