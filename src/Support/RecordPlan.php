<?php

declare(strict_types=1);

namespace Subdomains\Support;

/**
 * The DNS records a subdomain needs. Minecraft Java servers on other ports than 25565 also get an SRV record,
 * so players can join with just the name.
 */
final class RecordPlan
{
    private const int MINECRAFT_PORT = 25565;

    /** @return array<string, array<string, mixed>> Cloudflare record payloads keyed by kind. */
    public static function for(string $hostname, ?string $ipv4, ?string $ipv6, int $port, bool $minecraft, int $ttl = 1): array
    {
        $records = [];

        if ($ipv4 !== null) {
            $records['a'] = ['type' => 'A', 'name' => $hostname, 'content' => $ipv4, 'ttl' => $ttl, 'proxied' => false];
        }

        if ($ipv6 !== null) {
            $records['aaaa'] = ['type' => 'AAAA', 'name' => $hostname, 'content' => $ipv6, 'ttl' => $ttl, 'proxied' => false];
        }

        if ($minecraft && $port !== self::MINECRAFT_PORT && $records !== []) {
            $records['srv'] = [
                'type' => 'SRV',
                'name' => "_minecraft._tcp.{$hostname}",
                'ttl' => $ttl,
                'data' => ['priority' => 0, 'weight' => 5, 'port' => $port, 'target' => $hostname],
            ];
        }

        return $records;
    }

    public static function isValidName(string $name): bool
    {
        return preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name) === 1;
    }

    /** A public IPv4 address, or null for wildcard, private and invalid addresses. */
    public static function publicIpv4(?string $ip): ?string
    {
        return $ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false ? $ip : null;
    }
}
