<?php

declare(strict_types=1);

namespace Subdomains\Support;

/** Subdomain limits: -1 is unlimited, 0 allows none. */
final class Limits
{
    public const int UNLIMITED = -1;

    public static function isFull(int $count, int $limit): bool
    {
        return $limit !== self::UNLIMITED && $count >= $limit;
    }
}
