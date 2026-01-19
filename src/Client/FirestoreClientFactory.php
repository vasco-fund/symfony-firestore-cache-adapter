<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vasco\FirestoreCache\Client\Http\AccessTokenRetriever;
use Vasco\FirestoreCache\Client\Http\FirestoreHttpClient;

/**
 * Factory for creating Firestore client instances.
 */
final class FirestoreClientFactory
{
    public function __construct(private AccessTokenRetriever $accessTokenRetriever)
    {
    }

    /**
     * Create a Firestore client based on configuration.
     *
     * @param array<string, mixed> $options Configuration options:
     *                                      - use_sdk: Whether to use Google Cloud SDK (default: false)
     *                                      - projectId: GCP project ID (required)
     *                                      - database: Firestore database name (default: '(default)')
     *                                      - accessToken: Access token for HTTP client (required if use_sdk=false)
     *                                      - httpClient: Custom HTTP client instance (optional)
     *                                      - keyFilePath: Path to service account key file (for SDK)
     *                                      - authCache: PSR-6 cache for auth tokens (for SDK)
     */
    public function create(array $options): FirestoreClientInterface
    {
        $useSdk = $options['use_sdk'] ?? false;

        if (!\is_bool($useSdk)) {
            throw new \InvalidArgumentException('use_sdk must be a boolean');
        }

        if ($useSdk) {
            throw new \OutOfBoundsException('use_sdk option is not implemented yet.');
        }

        return $this->createHttpClient($options);
    }

    /**
     * Create an HTTP-based client (no grpc extension required).
     *
     * @param array<string, mixed> $options
     */
    public function createHttpClient(array $options): FirestoreHttpClient
    {
        if (!isset($options['projectId']) || !\is_string($options['projectId'])) {
            throw new \InvalidArgumentException('projectId must be a string');
        }

        $tokenRetriever = $this->accessTokenRetriever;
        if (isset($options['accessToken'])) {
            if (!\is_string($options['accessToken'])) {
                throw new \InvalidArgumentException('accessToken must be a string');
            }

            $tokenRetriever = new class($options['accessToken']) extends AccessTokenRetriever {
                public function __construct(private string $token)
                {
                }

                public function getToken(): string
                {
                    return $this->token;
                }
            };
        }

        $database = $options['database'] ?? '(default)';
        if (!\is_string($database)) {
            throw new \InvalidArgumentException('database must be a string');
        }

        $httpClient = $options['httpClient'] ?? null;
        if ($httpClient !== null && !$httpClient instanceof HttpClientInterface) {
            throw new \InvalidArgumentException('httpClient must implement HttpClientInterface');
        }

        return new FirestoreHttpClient(
            projectId: $options['projectId'],
            database: $database,
            httpClient: $httpClient,
            accessTokenRetriever: $tokenRetriever
        );
    }
}
