<?php

declare(strict_types=1);

namespace Subdomains\Support;

use Closure;

/**
 * Where a subdomain points: the addresses of the server's allocation, or a hostname for nodes that are only reachable by name.
 */
final readonly class Target
{
    public const array MODES = ['ip', 'alias', 'hostname'];

    public function __construct(
        public ?string $ipv4,
        public ?string $ipv6,
        public ?string $hostname,
        public int $port,
        public bool $preferHostname = false,
    ) {}

    /**
     * Modes: "ip" uses the node's addresses or the allocation's, "alias" uses the allocation's alias (an address or a
     * hostname) and "hostname" points everything at the node's hostname.
     *
     * @param  array{mode?: string|null, ipv4?: string|null, ipv6?: string|null, hostname?: string|null}  $node
     * @param  Closure(string): string  $lookup  Finds the IPv4 address of a hostname.
     */
    public static function for(array $node, string $nodeFqdn, string $ip, ?string $alias, int $port, Closure $lookup): self
    {
        $mode = $node['mode'] ?? 'ip';
        $ipv6 = ($node['ipv6'] ?? null) ?: null;
        $hostname = self::hostname($node['hostname'] ?? null) ?? self::hostname($nodeFqdn);
        $alias = $alias === null ? null : mb_strtolower(mb_trim($alias));

        if ($mode === 'hostname' && $hostname !== null) {
            return new self(null, null, $hostname, $port, true);
        }

        if ($mode === 'alias' && $alias !== null && $alias !== '') {
            if (Names::publicIpv4($alias) !== null) {
                return new self($alias, $ipv6, $hostname, $port);
            }

            if (Names::publicIpv6($alias) !== null) {
                return new self(null, $alias, $hostname, $port);
            }

            if (Names::isHostname($alias)) {
                return new self(null, null, $alias, $port, true);
            }
        }

        $ipv4 = ($node['ipv4'] ?? null)
            ?: Names::publicIpv4($ip)
            ?: Names::publicIpv4($alias)
            ?: Names::publicIpv4($lookup($nodeFqdn));

        return new self($ipv4 ?: null, $ipv6, $hostname, $port);
    }

    /** The address users see and the {ip} placeholder. */
    public function address(): ?string
    {
        return $this->preferHostname ? $this->hostname : ($this->ipv4 ?? $this->ipv6 ?? $this->hostname);
    }

    private static function hostname(?string $name): ?string
    {
        $name = mb_strtolower(mb_rtrim(mb_trim((string) $name), '.'));

        return Names::isHostname($name) ? $name : null;
    }
}
