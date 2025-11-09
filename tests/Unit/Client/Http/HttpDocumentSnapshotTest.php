<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Tests\Unit\Client\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Vasco\FirestoreCache\Client\Http\HttpDocumentSnapshot;

#[CoversClass(HttpDocumentSnapshot::class)]
final class HttpDocumentSnapshotTest extends TestCase
{
    public function testExistsReturnsFalseFor404(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $snapshot = new HttpDocumentSnapshot('doc', $response);

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

        $snapshot = new HttpDocumentSnapshot('doc', $response);

        $data = $snapshot->data();

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

        $snapshot = new HttpDocumentSnapshot('doc', $response);

        self::assertNull($snapshot->data());
    }

    public function testFromApiResponseBuildsSnapshot(): void
    {
        $snapshot = HttpDocumentSnapshot::fromApiResponse('doc', [
            'fields' => [
                'key' => ['stringValue' => 'value'],
            ],
        ]);

        self::assertSame('doc', $snapshot->id());
        self::assertTrue($snapshot->exists());
        self::assertSame(['key' => 'value'], $snapshot->data());
    }
}
