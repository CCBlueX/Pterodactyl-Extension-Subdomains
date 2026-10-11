<?php

declare(strict_types=1);

namespace Subdomains\Support;

use RuntimeException;
use Subdomains\Dns\DnsRecord;

/**
 * Which DNS records a subdomain gets. The SRV target, SRV port and comment can use placeholders like {fqdn}.
 */
final readonly class Template
{
    /** "ip" is A plus AAAA when the node has an IPv6 address. */
    public const array ADDRESSES = ['ip', 'a', 'aaaa', 'cname'];

    public const array PROTOCOLS = ['tcp', 'udp'];

    public const array PLACEHOLDERS = ['name', 'fqdn', 'ip', 'port', 'server_name'];

    public const int MAX_TTL = 86400;

    public function __construct(
        public string $address = 'ip',
        public bool $srv = false,
        public string $service = '_minecraft',
        public string $protocol = 'tcp',
        public int $priority = 0,
        public int $weight = 5,
        public string $srvPort = '{port}',
        public string $srvTarget = '{fqdn}',
        public ?int $skipPort = null,
        public int $ttl = DnsRecord::AUTO_TTL,
        public bool $proxied = false,
        public string $comment = '',
    ) {}

    /** The template for eggs without their own. */
    public static function standard(): self
    {
        return new self;
    }

    /** Minecraft Java servers on other ports than 25565 get an SRV record, so players join without a port. */
    public static function minecraft(): self
    {
        return new self(srv: true, skipPort: 25565);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $defaults = new self;
        $service = mb_strtolower(mb_trim((string) ($data['service'] ?? $defaults->service)));

        return new self(
            in_array($data['address'] ?? null, self::ADDRESSES, true) ? $data['address'] : $defaults->address,
            (bool) ($data['srv'] ?? false),
            $service === '' ? $defaults->service : '_'.mb_ltrim($service, '_'),
            in_array($data['protocol'] ?? null, self::PROTOCOLS, true) ? $data['protocol'] : $defaults->protocol,
            (int) ($data['priority'] ?? $defaults->priority),
            (int) ($data['weight'] ?? $defaults->weight),
            mb_trim((string) ($data['srv_port'] ?? '')) ?: $defaults->srvPort,
            mb_trim((string) ($data['srv_target'] ?? '')) ?: $defaults->srvTarget,
            isset($data['skip_port']) && $data['skip_port'] !== '' ? (int) $data['skip_port'] : null,
            (int) ($data['ttl'] ?? $defaults->ttl),
            (bool) ($data['proxied'] ?? false),
            mb_trim((string) ($data['comment'] ?? '')),
        );
    }

    /** @param array<string, string> $values */
    public static function render(string $text, array $values): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', static fn (array $match): string => $values[$match[1]] ?? $match[0], $text);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'srv' => $this->srv,
            'service' => $this->service,
            'protocol' => $this->protocol,
            'priority' => $this->priority,
            'weight' => $this->weight,
            'srv_port' => $this->srvPort,
            'srv_target' => $this->srvTarget,
            'skip_port' => $this->skipPort,
            'ttl' => $this->ttl,
            'proxied' => $this->proxied,
            'comment' => $this->comment,
        ];
    }

    /** Why the template cannot be saved, or null when it is fine. */
    public function problem(): ?string
    {
        foreach (['SRV target' => $this->srvTarget, 'SRV port' => $this->srvPort, 'comment' => $this->comment] as $field => $text) {
            preg_match_all('/\{([^}]*)\}/', $text, $matches);
            $unknown = array_diff($matches[1], self::PLACEHOLDERS);

            if ($unknown !== []) {
                return "The {$field} uses an unknown placeholder: {".reset($unknown).'}.';
            }
        }

        return match (true) {
            $this->proxied && $this->srv => 'Proxied records cannot have an SRV record. Turn off one of them.',
            $this->ttl !== DnsRecord::AUTO_TTL && ($this->ttl < 60 || $this->ttl > self::MAX_TTL) => 'Use a TTL between 60 and 86400 seconds, or automatic.',
            preg_match('/^_[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $this->service) !== 1 => 'Use a service like _minecraft: letters, numbers and dashes.',
            preg_match('/^(\{port\}|\d{1,5})$/', $this->srvPort) !== 1 => 'The SRV port must be {port} or a number.',
            preg_match('/^[a-z0-9._{}-]+$/i', $this->srvTarget) !== 1 => 'The SRV target must be a hostname, for example {fqdn}.',
            default => null,
        };
    }

    /**
     * @return list<DnsRecord>
     *
     * @throws RuntimeException when the server has no address the template can point to
     */
    public function records(Target $target, string $name, string $fqdn, string $serverName): array
    {
        $values = ['name' => $name, 'fqdn' => $fqdn, 'ip' => (string) $target->address(), 'port' => (string) $target->port, 'server_name' => $serverName];
        $comment = mb_substr(self::render($this->comment, $values), 0, 100);
        $proxied = $this->proxied && ! $this->srv;
        $records = [];

        if ($target->preferHostname || $this->address === 'cname') {
            $records[] = new DnsRecord('CNAME', $fqdn, $target->hostname ?? throw new RuntimeException('The node has no hostname to point to. An administrator can set one.'), $this->ttl, $proxied, comment: $comment);
        } else {
            if ($target->ipv4 !== null && $this->address !== 'aaaa') {
                $records[] = new DnsRecord('A', $fqdn, $target->ipv4, $this->ttl, $proxied, comment: $comment);
            }

            if ($target->ipv6 !== null && $this->address !== 'a') {
                $records[] = new DnsRecord('AAAA', $fqdn, $target->ipv6, $this->ttl, $proxied, comment: $comment);
            }

            if ($records === []) {
                throw new RuntimeException(match ($this->address) {
                    'a' => 'The server has no public IPv4 address. An administrator can set one for its node.',
                    'aaaa' => 'The server has no public IPv6 address. An administrator can set one for its node.',
                    default => 'The server has no public address. An administrator can set one for its node.',
                });
            }
        }

        if ($this->srv && $this->skipPort !== $target->port) {
            $records[] = $this->srvRecord($fqdn, $values, $comment);
        }

        return $records;
    }

    /** @param array<string, string> $values */
    private function srvRecord(string $fqdn, array $values, string $comment): DnsRecord
    {
        $port = self::render($this->srvPort, $values);
        $target = mb_strtolower(mb_rtrim(self::render($this->srvTarget, $values), '.'));

        if (! ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            throw new RuntimeException("The SRV port {$port} is not a valid port.");
        }

        if (! Names::isHostname($target)) {
            throw new RuntimeException("The SRV target {$target} is not a hostname.");
        }

        return new DnsRecord('SRV', "{$this->service}._{$this->protocol}.{$fqdn}", $target, $this->ttl, false, $this->priority, $this->weight, (int) $port, $comment);
    }
}
