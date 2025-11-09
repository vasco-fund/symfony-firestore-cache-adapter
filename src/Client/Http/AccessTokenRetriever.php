<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client\Http;

use Google\Auth\ApplicationDefaultCredentials;

class AccessTokenRetriever
{
    public function getToken(): string
    {
        $authToken = ApplicationDefaultCredentials::getCredentials()->fetchAuthToken();

        if (isset($authToken['access_token']) && \is_string($authToken['access_token'])) {
            return $authToken['access_token'];
        }

        throw new \RuntimeException('Failed to retrieve access token');
    }
}
