<?php

declare(strict_types=1);

namespace MichalSkoula\CodeIgniterAITranslation;

class ArrayTrans
{
    public static function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix . ($prefix !== '' ? '.' : '') . (string) $key;

            if (is_array($value)) {
                if ($value === []) {
                    $result[$newKey] = [];
                    continue;
                }

                foreach (self::flattenArray($value, $newKey) as $nestedKey => $nestedValue) {
                    $result[$nestedKey] = $nestedValue;
                }

                continue;
            }

            $result[$newKey] = $value;
        }

        return $result;
    }

    public static function unflattenArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $keys = explode('.', (string) $key);

            $current = &$result;

            foreach ($keys as $i => $segment) {
                if ($i === count($keys) - 1) {
                    $current[$segment] = $value;
                    continue;
                }

                if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                    $current[$segment] = [];
                }

                $current = &$current[$segment];
            }
        }

        return $result;
    }

    public static function arrayToString(array $array, int $indentLevel = 1): string
    {
        $output = "[\n";

        foreach ($array as $key => $value) {
            $output .= str_repeat('    ', $indentLevel);
            $output .= self::formatKey($key) . ' => ';

            if (is_array($value)) {
                $output .= self::arrayToString($value, $indentLevel + 1);
            } else {
                $output .= self::formatString((string) $value);
            }

            $output .= ",\n";
        }

        $output .= str_repeat('    ', $indentLevel - 1) . ']';

        return $output;
    }

    private static function formatString(string $value): string
    {
        if (str_contains($value, "'")) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }
        return "'" . $value . "'";
    }

    private static function formatKey(int|string $key): string
    {
        if (is_int($key)) {
            return (string) $key;
        }

        return self::formatString($key);
    }
}
