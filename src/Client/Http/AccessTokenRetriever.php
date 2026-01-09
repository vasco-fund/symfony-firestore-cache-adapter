<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client\Http;

use Google\Auth\ApplicationDefaultCredentials;

class AccessTokenRetriever
{
    private string $accessToken;

    public function getToken(): string
    {
        if (!isset($this->accessToken)) {
            $authToken = ApplicationDefaultCredentials::getCredentials()->fetchAuthToken();

            if (isset($authToken['access_token']) && \is_string($authToken['access_token'])) {
                $this->accessToken = $authToken['access_token'];
            } else {
                throw new \RuntimeException('Failed to retrieve access token');
            }
        }

        return $this->accessToken;
    }
}
