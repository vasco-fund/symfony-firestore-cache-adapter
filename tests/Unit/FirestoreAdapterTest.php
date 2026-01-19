<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Vasco\FirestoreCache\Client\BatchInterface;
use Vasco\FirestoreCache\Client\DocumentSnapshotInterface;
use Vasco\FirestoreCache\Client\FirestoreClientInterface;
use Vasco\FirestoreCache\FirestoreAdapter;

#[CoversClass(FirestoreAdapter::class)]
final class FirestoreAdapterTest extends TestCase
{
    public function testDoFetchReturnsUnmarshalledValues(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $client = $this->createMock(FirestoreClientInterface::class);

        $snapshotHit = $this->createMock(DocumentSnapshotInterface::class);
        $snapshotHit->method('exists')->willReturn(true);
        $snapshotHit->method('getData')->willReturn([
            'expiry' => time() + 60,
            'value' => 'serialized',
        ]);

        $snapshotExpired = $this->createMock(DocumentSnapshotInterface::class);
        $snapshotExpired->method('exists')->willReturn(true);
        $snapshotExpired->method('getData')->willReturn([
            'expiry' => time() - 60,
            'value' => 'stale',
        ]);

        $snapshotMissing = $this->createMock(DocumentSnapshotInterface::class);
        $snapshotMissing->method('exists')->willReturn(false);

        $client->method('getDocument')->willReturnMap([
            ['cache', 'hit', $snapshotHit],
            ['cache', 'expired', $snapshotExpired],
            ['cache', 'missing', $snapshotMissing],
        ]);

        $marshaller->expects($this->once())
            ->method('unmarshall')
            ->with('serialized')
            ->willReturn('value');

        $adapter = new TestableFirestoreAdapter('ns', 0, $marshaller, $client, 'cache');

        $result = $adapter->fetch(['missing', 'hit', 'expired']);

        self::assertSame(['hit' => 'value'], $result);
    }

    public function testDoHaveDeletesExpiredItem(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $client = $this->createMock(FirestoreClientInterface::class);
        $batch = $this->createMock(BatchInterface::class);

        $snapshot = $this->createMock(DocumentSnapshotInterface::class);
        $snapshot->method('exists')->willReturn(true);
        $snapshot->method('getData')->willReturn([
            'expiry' => time() - 60,
        ]);

        $client->expects($this->once())
            ->method('getDocument')
            ->with('cache', 'stale')
            ->willReturn($snapshot);

        $client->expects($this->once())
            ->method('createBatch')
            ->willReturn($batch);

        $batch->expects($this->once())
            ->method('delete')
            ->with('cache', 'stale')
            ->willReturnSelf();

        $batch->expects($this->once())
            ->method('commit');

        $adapter = new TestableFirestoreAdapter('ns', 0, $marshaller, $client, 'cache');

        self::assertFalse($adapter->have('stale'));
    }

    public function testDoHaveReturnsTrueForFreshItem(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $client = $this->createMock(FirestoreClientInterface::class);

        $snapshot = $this->createMock(DocumentSnapshotInterface::class);
        $snapshot->method('exists')->willReturn(true);
        $snapshot->method('getData')->willReturn([
            'expiry' => time() + 60,
        ]);

        $client->expects($this->once())
            ->method('getDocument')
            ->with('cache', 'fresh')
            ->willReturn($snapshot);

        $adapter = new TestableFirestoreAdapter('ns', 0, $marshaller, $client, 'cache');

        self::assertTrue($adapter->have('fresh'));
    }

    public function testDoClearDeletesCollectionWhenNamespaceEmpty(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $client = $this->createMock(FirestoreClientInterface::class);

        $client->expects($this->once())
            ->method('deleteCollection')
            ->with('cache', 500);

        $adapter = new TestableFirestoreAdapter('', 0, $marshaller, $client, 'cache');

        self::assertTrue($adapter->clearNamespace(''));
    }

    public function testDoClearDeletesNamespacedDocuments(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $client = $this->createMock(FirestoreClientInterface::class);
        $batch = $this->createMock(BatchInterface::class);

        $doc1 = $this->createMock(DocumentSnapshotInterface::class);
        $doc1->method('getId')->willReturn('ns:key1');

        $doc2 = $this->createMock(DocumentSnapshotInterface::class);
        $doc2->method('getId')->willReturn('other:key');

        $doc3 = $this->createMock(DocumentSnapshotInterface::class);
        $doc3->method('getId')->willReturn('ns:key2');

        $client->expects($this->once())
            ->method('queryDocuments')
            ->with('cache', '__name__', 'GREATER_THAN_OR_EQUAL', 'ns:')
            ->willReturn([$doc1, $doc2, $doc3]);

        $client->expects($this->once())
            ->method('createBatch')
            ->willReturn($batch);

        $batch->expects($this->once())
            ->method('delete')
            ->with('cache', 'ns:key1')
            ->willReturnSelf();

        $batch->expects($this->once())
            ->method('commit');

        $adapter = new TestableFirestoreAdapter('ns:', 0, $marshaller, $client, 'cache');

        self::assertTrue($adapter->clearNamespace('ns:'));
    }

    public function testDoSaveBatchesMarshalledValues(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $client = $this->createMock(FirestoreClientInterface::class);
        $batch = $this->createMock(BatchInterface::class);

        $marshaller->expects($this->once())
            ->method('marshall')
            ->with(['item' => 'value'], $this->anything())
            ->willReturn(['item' => 'serialized']);

        $client->expects($this->once())
            ->method('createBatch')
            ->willReturn($batch);

        $batch->expects($this->once())
            ->method('set')
            ->with('cache', 'item', [
                'value' => 'serialized',
                'expiry' => null,
            ])
            ->willReturnSelf();

        $batch->expects($this->once())
            ->method('commit');

        $adapter = new TestableFirestoreAdapter('ns', 0, $marshaller, $client, 'cache');

        self::assertTrue($adapter->saveItems(['item' => 'value'], 0));
    }

    public function testDoSaveSetsExpiryWhenLifetimeProvided(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $client = $this->createMock(FirestoreClientInterface::class);
        $batch = $this->createMock(BatchInterface::class);

        $marshaller->expects($this->once())
            ->method('marshall')
            ->willReturn(['item' => 'serialized']);

        $client->expects($this->once())
            ->method('createBatch')
            ->willReturn($batch);

        $start = time();

        $batch->expects($this->once())
            ->method('set')
            ->with(
                'cache',
                'item',
                $this->callback(function (array $data) use ($start): bool {
                    $expiry = $data['expiry'] ?? null;

                    return $expiry !== null
                        && $expiry >= $start + 55
                        && $expiry <= $start + 61;
                })
            )
            ->willReturnSelf();

        $batch->expects($this->once())
            ->method('commit');

        $adapter = new TestableFirestoreAdapter('ns', 0, $marshaller, $client, 'cache');

        self::assertTrue($adapter->saveItems(['item' => 'value'], 60));
    }

    public function testDoDeleteReturnsTrueOnSuccess(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $client = $this->createMock(FirestoreClientInterface::class);
        $batch = $this->createMock(BatchInterface::class);

        $client->expects($this->once())
            ->method('createBatch')
            ->willReturn($batch);

        $batch->expects($this->once())
            ->method('delete')
            ->with('cache', 'key')
            ->willReturnSelf();

        $batch->expects($this->once())
            ->method('commit');

        $adapter = new TestableFirestoreAdapter('ns', 0, $marshaller, $client, 'cache');

        self::assertTrue($adapter->deleteItems(['key']));
    }

    public function testDoClearCommitsInMultipleBatches(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $client = $this->createMock(FirestoreClientInterface::class);
        $batch1 = $this->createMock(BatchInterface::class);
        $batch2 = $this->createMock(BatchInterface::class);

        $docs = [];
        for ($i = 0; $i < 501; ++$i) {
            $doc = $this->createMock(DocumentSnapshotInterface::class);
            $doc->method('getId')->willReturn('ns:key' . $i);
            $docs[] = $doc;
        }

        $client->expects($this->once())
            ->method('queryDocuments')
            ->willReturn($docs);

        $client->expects($this->exactly(2))
            ->method('createBatch')
            ->willReturnOnConsecutiveCalls($batch1, $batch2);

        $batch1->expects($this->exactly(500))
            ->method('delete')
            ->willReturnSelf();
        $batch2->expects($this->once())
            ->method('delete')
            ->willReturnSelf();

        $batch1->expects($this->once())->method('commit');
        $batch2->expects($this->once())->method('commit');

        $adapter = new TestableFirestoreAdapter('ns:', 0, $marshaller, $client, 'cache');

        self::assertTrue($adapter->clearNamespace('ns:'));
    }

    public function testDoClearDoesNotCommitWhenNoDocuments(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $client = $this->createMock(FirestoreClientInterface::class);
        $batch = $this->createMock(BatchInterface::class);

        $client->expects($this->once())
            ->method('queryDocuments')
            ->willReturn([]);

        $client->expects($this->once())
            ->method('createBatch')
            ->willReturn($batch);

        $batch->expects($this->never())
            ->method('commit');

        $adapter = new TestableFirestoreAdapter('ns:', 0, $marshaller, $client, 'cache');

        self::assertTrue($adapter->clearNamespace('ns:'));
    }

    public function testInvalidNamespaceThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $client = $this->createMock(FirestoreClientInterface::class);

        $this->expectException(\Symfony\Component\Cache\Exception\InvalidArgumentException::class);

        new TestableFirestoreAdapter('bad::namespace', 0, $marshaller, $client, 'cache');
    }

    public function testDoSaveReturnsFailedKeysWhenMarshallerReturnsEmpty(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $client = $this->createMock(FirestoreClientInterface::class);

        $marshaller->method('marshall')->willReturnCallback(
            function (array $values, ?array &$failed): array {
                $failed = ['bad'];

                return [];
            }
        );

        $adapter = new TestableFirestoreAdapter('ns', 0, $marshaller, $client, 'cache');

        self::assertSame(['bad'], $adapter->saveItems(['bad' => 'value'], 10));
    }
}

final class TestableFirestoreAdapter extends FirestoreAdapter
{
    public function fetch(array $ids): array
    {
        return $this->doFetch($ids);
    }

    public function have(string $id): bool
    {
        return $this->doHave($id);
    }

    public function clearNamespace(string $namespace): bool
    {
        return $this->doClear($namespace);
    }

    public function saveItems(array $values, int $lifetime): array|bool
    {
        return $this->doSave($values, $lifetime);
    }

    public function deleteItems(array $ids): bool
    {
        return $this->doDelete($ids);
    }
}
