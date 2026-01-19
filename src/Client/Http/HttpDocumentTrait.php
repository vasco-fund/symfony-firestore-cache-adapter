<?php

declare(strict_types=1);

namespace Vasco\FirestoreCache\Client\Http;

trait HttpDocumentTrait
{
    /**
     * Parse Firestore field format to PHP values.
     *
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private static function parseFields(array $fields): array
    {
        $data = [];

        foreach ($fields as $key => $field) {
            $data[$key] = self::parseFieldValue($field);
        }

        return $data;
    }

    /**
     * Parse a single Firestore field value.
     */
    private static function parseFieldValue(mixed $field): mixed
    {
        if (!\is_array($field)) {
            return null;
        }

        // Handle different Firestore value types
        if (isset($field['stringValue'])) {
            return $field['stringValue'];
        }

        if (isset($field['bytesValue'])) {
            // Decode base64-encoded binary data
            $decoded = base64_decode($field['bytesValue'], true);

            return $decoded !== false ? $decoded : $field['bytesValue'];
        }

        if (isset($field['integerValue'])) {
            return (int) $field['integerValue'];
        }

        if (isset($field['doubleValue'])) {
            return (float) $field['doubleValue'];
        }

        if (isset($field['booleanValue'])) {
            return (bool) $field['booleanValue'];
        }

        if (isset($field['timestampValue'])) {
            return $field['timestampValue'];
        }

        if (isset($field['arrayValue']['values'])) {
            $values = [];
            foreach ($field['arrayValue']['values'] as $value) {
                $values[] = self::parseFieldValue($value);
            }

            return $values;
        }

        if (isset($field['mapValue']['fields'])) {
            return self::parseFields($field['mapValue']['fields']);
        }

        if (isset($field['nullValue'])) {
            return null;
        }

        return null;
    }
}
