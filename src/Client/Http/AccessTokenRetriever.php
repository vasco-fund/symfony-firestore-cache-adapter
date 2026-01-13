<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client\Http;

use Google\Auth\ApplicationDefaultCredentials;
use Symfony\Component\Cache\ResettableInterface;

class AccessTokenRetriever implements ResettableInterface
{
    private ?string $accessToken;

    public function getToken(): string
    {
        if (!isset($this->accessToken)) {
            $authToken = ApplicationDefaultCredentials::getCredentials()->fetchAuthToken();

            if (false === isset($authToken['access_token']) || false === \is_string($authToken['access_token'])) {
                throw new \RuntimeException('Failed to retrieve access token');
            }

            $this->accessToken = $authToken['access_token'];

            return $authToken['access_token'];
        }

        return $this->accessToken;
    }

    public function reset()
    {
        $this->accessToken = null;
    }
}
