<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Tests\Unit\Client\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vasco\FirestoreCache\Client\Http\HttpDocumentSnapshot;

#[CoversClass(HttpDocumentSnapshot::class)]
final class HttpDocumentSnapshotTest extends TestCase
{
    public function testFromApiResponseBuildsSnapshot(): void
    {
        $snapshot = HttpDocumentSnapshot::fromApiResponse('doc', [
            'fields' => [
                'key' => ['stringValue' => 'value'],
            ],
        ]);

        self::assertSame('doc', $snapshot->getId());
        self::assertTrue($snapshot->exists());
        self::assertSame(['key' => 'value'], $snapshot->getData());
    }
}
