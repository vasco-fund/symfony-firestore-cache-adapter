<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache;

use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Vasco\FirestoreCache\Client\FirestoreClientInterface;

/**
 * Symfony Cache adapter for Firestore.
 *
 * Stores cache items as documents in a Firestore collection.
 */
class FirestoreAdapter extends AbstractAdapter
{
    private const METADATA_EXPIRY_FIELD = 'expiry';
    private const VALUE_FIELD = 'value';

    private FirestoreClientInterface $client;
    private string $collection;
    private MarshallerInterface $marshaller;

    public function __construct(
        string $namespace,
        int $defaultLifetime,
        ?MarshallerInterface $marshaller,
        FirestoreClientInterface $client,
        string $collection = 'symfony_cache',
    ) {
        $this->client = $client;
        $this->collection = $collection;
        $this->marshaller = $marshaller ?? new DefaultMarshaller();

        parent::__construct($namespace, $defaultLifetime);
    }

    /**
     * @param string[] $ids
     *
     * @return array<string, mixed>
     *
     * @phpstan-ignore method.childParameterType
     */
    protected function doFetch(array $ids): iterable
    {
        $results = [];

        $snapshots = [];
        foreach ($ids as $id) {
            try {
                $snapshots[$id] = $this->client->getDocument($this->collection, $id);
            } catch (\Exception $e) {
                // Skip non-existent or corrupted entries
                continue;
            }
        }

        foreach ($snapshots as $id => $snapshot) {
            try {
                if (!$snapshot->exists()) {
                    continue;
                }

                $data = $snapshot->getData();
                $expiry = $data[self::METADATA_EXPIRY_FIELD] ?? null;

                // Check expiration
                if ($expiry !== null && $expiry < time()) {
                    continue;
                }

                $value = $data[self::VALUE_FIELD] ?? null;
                if (\is_string($value) === false) {
                    continue;
                }

                $results[$id] = $this->marshaller->unmarshall($value);
            } catch (\Exception $e) {
                // Skip non-existent or corrupted entries
                continue;
            }
        }

        return $results;
    }

    protected function doHave(string $id): bool
    {
        try {
            $snapshot = $this->client->getDocument($this->collection, $id);

            if (!$snapshot->exists()) {
                return false;
            }

            $data = $snapshot->getData();
            $expiry = $data[self::METADATA_EXPIRY_FIELD] ?? null;

            // Check if expired
            if ($expiry !== null && $expiry < time()) {
                // Expired, delete it asynchronously
                $this->doDelete([$id]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function doClear(string $namespace): bool
    {
        try {
            if ($namespace === '') {
                // Clear entire collection
                $this->client->deleteCollection($this->collection, 500);
            } else {
                // Query and delete documents with matching namespace prefix
                $docs = $this->client->queryDocuments(
                    $this->collection,
                    '__name__',
                    'GREATER_THAN_OR_EQUAL',
                    $namespace
                );

                $batch = $this->client->createBatch();
                $count = 0;

                foreach ($docs as $doc) {
                    $docId = $doc->getId();
                    if (!str_starts_with($docId, $namespace)) {
                        break;
                    }

                    $batch->delete($this->collection, $docId);
                    ++$count;

                    if ($count >= 500) {
                        $batch->commit();
                        $batch = $this->client->createBatch();
                        $count = 0;
                    }
                }

                if ($count > 0) {
                    $batch->commit();
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param string[] $ids
     *
     * @phpstan-ignore method.childParameterType
     */
    protected function doDelete(array $ids): bool
    {
        try {
            $batch = $this->client->createBatch();

            foreach ($ids as $id) {
                $batch->delete($this->collection, $id);
            }

            $batch->commit();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>|bool
     *
     * @phpstan-ignore method.childParameterType
     */
    protected function doSave(array $values, int $lifetime): array|bool
    {
        $values = $this->marshaller->marshall($values, $failed);
        if (\count($values) === 0) {
            return $failed;
        }

        try {
            $batch = $this->client->createBatch();
            $expiry = $lifetime > 0 ? time() + $lifetime : null;

            foreach ($values as $id => $value) {
                $data = [
                    self::VALUE_FIELD => $value,
                    self::METADATA_EXPIRY_FIELD => $expiry,
                ];

                $batch->set($this->collection, $id, $data);
            }

            $batch->commit();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
