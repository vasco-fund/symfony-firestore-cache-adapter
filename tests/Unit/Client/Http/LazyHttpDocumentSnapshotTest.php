<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Tests\Unit\Client\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Vasco\FirestoreCache\Client\Http\LazyHttpDocumentSnapshot;

#[CoversClass(LazyHttpDocumentSnapshot::class)]
final class LazyHttpDocumentSnapshotTest extends TestCase
{
    public function testExistsReturnsFalseFor404(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $snapshot = new LazyHttpDocumentSnapshot('doc', $response);

        self::assertFalse($snapshot->exists());
    }

    public function testDataParsesFirestoreFields(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'fields' => [
                'name' => ['stringValue' => 'cache'],
                'bytes' => ['bytesValue' => base64_encode("bin\xff")],
                'count' => ['integerValue' => '5'],
                'ratio' => ['doubleValue' => '1.25'],
                'active' => ['booleanValue' => 1],
                'timestamp' => ['timestampValue' => '2024-01-01T00:00:00Z'],
                'list' => [
                    'arrayValue' => [
                        'values' => [
                            ['stringValue' => 'a'],
                            ['integerValue' => 2],
                            ['nullValue' => null],
                        ],
                    ],
                ],
                'map' => [
                    'mapValue' => [
                        'fields' => [
                            'nested' => ['stringValue' => 'value'],
                        ],
                    ],
                ],
            ],
        ]);

        $snapshot = new LazyHttpDocumentSnapshot('doc', $response);

        $data = $snapshot->getData();

        self::assertIsArray($data);
        self::assertSame('cache', $data['name']);
        self::assertSame("bin\xff", $data['bytes']);
        self::assertSame(5, $data['count']);
        self::assertSame(1.25, $data['ratio']);
        self::assertIsFloat($data['ratio']);
        self::assertTrue($data['active']);
        self::assertIsBool($data['active']);
        self::assertSame('2024-01-01T00:00:00Z', $data['timestamp']);
        self::assertSame(['a', 2, null], $data['list']);
        self::assertSame(['nested' => 'value'], $data['map']);
    }

    public function testDataReturnsNullWhenPayloadEmpty(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([]);

        $snapshot = new LazyHttpDocumentSnapshot('doc', $response);

        self::assertNull($snapshot->getData());
    }
}
