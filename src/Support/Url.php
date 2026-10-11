<?php

declare(strict_types=1);

namespace Subdomains\Support;

/**
 * Keeps API URLs administrators enter from pointing the panel at its own network, unless they allow it.
 */
final class Url
{
    /** The reason the URL may not be used, or null when it is fine. */
    public static function problem(string $url, bool $allowPrivate): ?string
    {
        $parts = parse_url($url);
        $scheme = mb_strtolower($parts['scheme'] ?? '');
        $host = mb_trim($parts['host'] ?? '', '[]');

        if ($host === '' || ! in_array($scheme, $allowPrivate ? ['https', 'http'] : ['https'], true)) {
            return $allowPrivate ? 'Use an http or https URL.' : 'Use an https URL, or allow private addresses in the extension settings.';
        }

        if ($allowPrivate) {
            return null;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::lookup($host);

        if ($addresses === []) {
            return "{$host} could not be found.";
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return "{$host} points to a private address. Allow private addresses in the extension settings to use it.";
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function lookup(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null, $records)));
    }
}
