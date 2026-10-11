<?php

declare(strict_types=1);

namespace Subdomains\Support;

final class Names
{
    /** A single lowercase DNS label users can pick, like "play". */
    public static function isValidLabel(string $name): bool
    {
        return preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name) === 1;
    }

    /** A full hostname with at least two labels, like "node1.example.com". */
    public static function isHostname(string $name): bool
    {
        return mb_strlen($name) <= 253 && preg_match('/^([a-z0-9_]([a-z0-9_-]{0,61}[a-z0-9])?\.)+[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/i', $name) === 1;
    }

    /** A public IPv4 address, or null for wildcard, private and invalid addresses. */
    public static function publicIpv4(?string $ip): ?string
    {
        return $ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false ? $ip : null;
    }

    /** A public IPv6 address, or null. */
    public static function publicIpv6(?string $ip): ?string
    {
        return $ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false ? $ip : null;
    }
}
