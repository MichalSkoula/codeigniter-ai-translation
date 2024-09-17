<?php

declare(strict_types=1);

namespace MichalSkoula\CodeIgniterAITranslation;

class ArrayTrans
{
    public static function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix . ($prefix ? '.' : '') . $key;
            if (is_array($value)) {
                $result = array_merge($result, self::flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
    }

    public static function unflattenArray(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $keys = explode('.', $key);
            $current = &$result;
            foreach ($keys as $i => $k) {
                if ($i === count($keys) - 1) {
                    $current[$k] = $value;
                } else {
                    if (! isset($current[$k]) || ! is_array($current[$k])) {
                        $current[$k] = [];
                    }
                    $current = &$current[$k];
                }
            }
        }
        return $result;
    }

    public static function arrayToString(array $array, int $indentLevel = 1): string
    {
        $output = "[\n";
        foreach ($array as $key => $value) {
            $output .= str_repeat('    ', $indentLevel);
            if (is_string($key)) {
                $output .= "'" . addslashes($key) . "' => ";
            } else {
                $output .= $key . ' => ';
            }
            if (is_array($value)) {
                $output .= self::arrayToString($value, $indentLevel + 1);
            } else {
                $output .= "'" . addslashes((string) $value) . "'";
            }
            $output .= ",\n";
        }
        $output .= str_repeat('    ', $indentLevel - 1) . ']';
        return $output;
    }
}
