<?php

declare(strict_types=1);

namespace Subdomains\Support;

/**
 * Names users cannot take: exact names and regular expressions, both ignoring case.
 */
final class Blacklist
{
    /**
     * Why the name cannot be used, or null when it is free to take.
     *
     * @param  list<string>  $names
     * @param  list<string>  $patterns
     */
    public static function reason(string $name, array $names, array $patterns): ?string
    {
        $name = mb_strtolower($name);

        if (in_array($name, array_map(static fn (string $item): string => mb_strtolower(mb_trim($item)), $names), true)) {
            return "\"{$name}\" is reserved.";
        }

        foreach ($patterns as $pattern) {
            if (@preg_match(self::expression($pattern), $name) === 1) {
                return "\"{$name}\" is reserved.";
            }
        }

        return null;
    }

    /** Why the pattern cannot be used, or null when it is a valid regular expression. */
    public static function problem(string $pattern): ?string
    {
        if (mb_trim($pattern) === '') {
            return 'Enter a pattern.';
        }

        return @preg_match(self::expression($pattern), '') === false ? "\"{$pattern}\" is not a valid regular expression." : null;
    }

    /** Patterns are written without delimiters, but "/^admin/" works too. */
    private static function expression(string $pattern): string
    {
        $pattern = mb_trim($pattern);

        if (mb_strlen($pattern) > 2 && str_starts_with($pattern, '/') && str_ends_with($pattern, '/')) {
            $pattern = mb_substr($pattern, 1, -1);
        }

        return '~'.str_replace('~', '\~', $pattern).'~iu';
    }
}
