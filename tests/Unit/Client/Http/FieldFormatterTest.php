<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Tests\Unit\Client\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vasco\FirestoreCache\Client\Http\FieldFormatter;

#[CoversClass(FieldFormatter::class)]
final class FieldFormatterTest extends TestCase
{
    public function testFormatValueCoversScalarTypes(): void
    {
        self::assertSame(['stringValue' => 'hello'], FieldFormatter::formatValue('hello'));
        self::assertSame(['integerValue' => 12], FieldFormatter::formatValue(12));
        self::assertSame(['doubleValue' => 1.5], FieldFormatter::formatValue(1.5));
        self::assertSame(['booleanValue' => true], FieldFormatter::formatValue(true));
        self::assertSame(['nullValue' => null], FieldFormatter::formatValue(null));

        $date = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        self::assertSame(
            ['timestampValue' => $date->format(\DateTime::ATOM)],
            FieldFormatter::formatValue($date)
        );
    }

    public function testFormatValueDetectsBinaryStrings(): void
    {
        $binary = "binary\xff";
        $formatted = FieldFormatter::formatValue($binary);

        self::assertArrayHasKey('bytesValue', $formatted);
        self::assertSame($binary, base64_decode($formatted['bytesValue'], true));
    }

    public function testFormatValueHandlesArrays(): void
    {
        $list = FieldFormatter::formatValue(['a', 1, false]);
        self::assertSame(
            ['arrayValue' => ['values' => [
                ['stringValue' => 'a'],
                ['integerValue' => 1],
                ['booleanValue' => false],
            ]]],
            $list
        );

        $map = FieldFormatter::formatValue(['key' => 'value', 'count' => 2]);
        self::assertSame(
            ['mapValue' => ['fields' => [
                'key' => ['stringValue' => 'value'],
                'count' => ['integerValue' => 2],
            ]]],
            $map
        );
    }

    public function testFormatFieldsFormatsAssociativeArray(): void
    {
        $formatted = FieldFormatter::formatFields(['name' => 'cache', 'size' => 1]);

        self::assertSame(
            [
                'name' => ['stringValue' => 'cache'],
                'size' => ['integerValue' => 1],
            ],
            $formatted
        );
    }

    public function testFormatValueRejectsUnsupportedTypes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FieldFormatter::formatValue(new \stdClass());
    }
}
