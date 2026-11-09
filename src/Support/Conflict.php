<?php

declare(strict_types=1);

namespace Subdomains\Support;

/** Decides whether a hostname is free at the DNS provider, given the record types already there. */
final class Conflict
{
    /** @param list<string> $types */
    public static function reason(string $hostname, array $types): ?string
    {
        $types = array_values(array_unique(array_filter($types, static fn (string $type): bool => $type !== '')));

        return $types === []
            ? null
            : "{$hostname} already has DNS records (".implode(', ', $types).') that this panel did not create. Pick another name, or remove them at the DNS provider first.';
    }
}
