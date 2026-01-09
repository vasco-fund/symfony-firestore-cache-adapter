<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Tests\Unit\Client\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vasco\FirestoreCache\Client\Http\BatchOperation;

#[CoversClass(BatchOperation::class)]
final class BatchOperationTest extends TestCase
{
    public function testCreatesOperationWithData(): void
    {
        $data = ['value' => 10];

        $operation = new BatchOperation('set', 'cache', 'doc', $data);

        self::assertSame('set', $operation->type);
        self::assertSame('cache', $operation->collection);
        self::assertSame('doc', $operation->documentId);
        self::assertSame($data, $operation->data);
    }

    public function testCreatesOperationWithoutData(): void
    {
        $operation = new BatchOperation('delete', 'cache', 'doc');

        self::assertSame('delete', $operation->type);
        self::assertSame('cache', $operation->collection);
        self::assertSame('doc', $operation->documentId);
        self::assertNull($operation->data);
    }

    public function testOperationIsReadonly(): void
    {
        $operation = new BatchOperation('set', 'cache', 'doc', ['value' => 10]);

        $this->expectException(\Error::class);

        $operation->type = 'delete';
    }
}
