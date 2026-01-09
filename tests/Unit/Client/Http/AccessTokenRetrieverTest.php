<?php

declare(strict_types=1);

namespace Google\Auth;

final class ApplicationDefaultCredentials
{
    public static ?object $credentials = null;

    public static function getCredentials(): object
    {
        if (null === self::$credentials) {
            throw new \RuntimeException('Credentials not set for test');
        }

        return self::$credentials;
    }
}

namespace Vasco\FirestoreCache\Tests\Unit\Client\Http;

use Google\Auth\ApplicationDefaultCredentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Vasco\FirestoreCache\Client\Http\AccessTokenRetriever;

#[CoversClass(AccessTokenRetriever::class)]
#[PreserveGlobalState(false)]
final class AccessTokenRetrieverTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationDefaultCredentials::$credentials = null;
    }

    public function testGetTokenReturnsAccessToken(): void
    {
        ApplicationDefaultCredentials::$credentials = new CredentialsStub(['access_token' => 'token']);

        $retriever = new AccessTokenRetriever();

        self::assertSame('token', $retriever->getToken());
    }

    public function testGetTokenCachesAccessToken(): void
    {
        $credentials = new CredentialsStub(['access_token' => 'token']);
        ApplicationDefaultCredentials::$credentials = $credentials;

        $retriever = new AccessTokenRetriever();

        self::assertSame('token', $retriever->getToken());
        self::assertSame('token', $retriever->getToken());
        self::assertSame(1, $credentials->fetchAuthTokenCalls);
    }

    public function testGetTokenThrowsWhenMissingToken(): void
    {
        ApplicationDefaultCredentials::$credentials = new CredentialsStub(['access_token' => null]);

        $retriever = new AccessTokenRetriever();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to retrieve access token');

        $retriever->getToken();
    }
}

final class CredentialsStub
{
    public int $fetchAuthTokenCalls = 0;

    /**
     * @param array<string, mixed> $token
     */
    public function __construct(private array $token)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchAuthToken(): array
    {
        ++$this->fetchAuthTokenCalls;

        return $this->token;
    }
}
