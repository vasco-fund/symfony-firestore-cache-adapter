<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client\Http;

/**
 * Helper class to format PHP values into Firestore field format.
 */
final class FieldFormatter
{
    /**
     * Format an array of values into Firestore field format.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, array<string, mixed>>
     */
    public static function formatFields(array $data): array
    {
        $fields = [];

        foreach ($data as $key => $value) {
            $fields[$key] = self::formatValue($value);
        }

        return $fields;
    }

    /**
     * Format a single value into Firestore field format.
     *
     * @return array<string, mixed>
     */
    public static function formatValue(mixed $value): array
    {
        return match (true) {
            \is_string($value) => self::formatStringValue($value),
            \is_int($value) => ['integerValue' => $value],
            \is_float($value) => ['doubleValue' => $value],
            \is_bool($value) => ['booleanValue' => $value],
            $value === null => ['nullValue' => null],
            $value instanceof \DateTimeInterface => ['timestampValue' => $value->format(\DateTime::ATOM)],
            \is_array($value) => self::formatArrayValue($value),
            default => throw new \InvalidArgumentException(\sprintf('Unsupported value type: %s', get_debug_type($value))),
        };
    }

    /**
     * Format a string value, detecting if it's binary data.
     *
     * @return array<string, mixed>
     */
    private static function formatStringValue(string $value): array
    {
        // Check if the string is valid UTF-8
        // If not, it's binary data and should be encoded as bytesValue
        if (!mb_check_encoding($value, 'UTF-8')) {
            return ['bytesValue' => base64_encode($value)];
        }

        return ['stringValue' => $value];
    }

    /**
     * Format an array value (could be list or map).
     *
     * @param array<mixed> $value
     *
     * @return array<string, mixed>
     */
    private static function formatArrayValue(array $value): array
    {
        // Check if it's a list (sequential numeric keys starting from 0)
        if (array_is_list($value)) {
            $values = [];
            foreach ($value as $item) {
                $values[] = self::formatValue($item);
            }

            return ['arrayValue' => ['values' => $values]];
        }

        // It's an associative array, treat as map
        return ['mapValue' => ['fields' => self::formatFields($value)]];
    }
}
