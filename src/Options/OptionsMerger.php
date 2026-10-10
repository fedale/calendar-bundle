<?php

namespace Fedale\CalendarBundle\Options;

/**
 * Deep merge of option trees. Unlike array_replace_recursive(), lists are
 * replaced as a whole: a calendar enabling ['list'] must not inherit the
 * second entry of a default ['month', 'list'] by index.
 */
final class OptionsMerger
{
    /**
     * @param array<string, mixed> ...$layers lowest precedence first
     *
     * @return array<string, mixed>
     */
    public static function merge(array ...$layers): array
    {
        $result = [];
        foreach ($layers as $layer) {
            $result = self::mergeTwo($result, $layer);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private static function mergeTwo(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $current = $base[$key] ?? null;
            if (self::isTree($current) && \is_array($value) && ($value === [] || self::isTree($value))) {
                // An empty array over a subtree overrides nothing; over a list
                // default (e.g. `hiddenDays`) it is a value and replaces it.
                $base[$key] = self::mergeTwo($current, $value);
                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }

    private static function isTree(mixed $value): bool
    {
        return \is_array($value) && $value !== [] && !array_is_list($value);
    }
}
