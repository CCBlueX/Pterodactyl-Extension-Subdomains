<?php

declare(strict_types=1);

namespace Subdomains\Dns;

/**
 * PowerDNS has no record ids: it replaces whole RRsets (all records of one name and type), so every change
 * reads the RRset first and keeps the records the extension did not make.
 */
final readonly class PowerDns implements Provider
{
    private const int AUTO_TTL = 300;

    private string $base;

    public function __construct(private Transport $http, string $url, private string $key, string $server = 'localhost')
    {
        $this->base = preg_replace('#/api/v1/?$#', '', mb_rtrim($url, '/')).'/api/v1/servers/'.rawurlencode($server ?: 'localhost');
    }

    /** The record data in PowerDNS' zone file format. */
    public static function content(DnsRecord $record): string
    {
        return match ($record->type) {
            'SRV' => "{$record->priority} {$record->weight} {$record->port} ".self::absolute($record->content),
            'CNAME' => self::absolute($record->content),
            default => $record->content,
        };
    }

    public function findZone(string $domain): Zone
    {
        foreach (Zone::candidates($domain) as $name) {
            $zones = $this->send('GET', '/zones?'.http_build_query(['zone' => self::absolute($name)]));

            if (isset($zones[0])) {
                return new Zone((string) ($zones[0]['id'] ?? self::absolute($name)), $name);
            }
        }

        throw new DnsException("PowerDNS has no zone for {$domain}.");
    }

    public function create(Zone $zone, DnsRecord $record): DnsRecord
    {
        $content = self::content($record);
        $records = $this->records($zone, $record);

        if (! in_array(mb_strtolower($content), array_map(static fn (array $item): string => mb_strtolower((string) $item['content']), $records), true)) {
            $records[] = ['content' => $content, 'disabled' => false];
        }

        $this->patch($zone, [
            'name' => self::absolute($record->name),
            'type' => $record->type,
            'ttl' => $record->ttl === DnsRecord::AUTO_TTL ? self::AUTO_TTL : $record->ttl,
            'changetype' => 'REPLACE',
            'records' => array_values($records),
        ]);

        return $record->withId(null);
    }

    public function delete(Zone $zone, DnsRecord $record): void
    {
        $content = mb_strtolower(self::content($record));
        $rrset = $this->rrset($zone, $record);
        $records = (array) ($rrset['records'] ?? []);
        $remaining = array_values(array_filter($records, static fn (array $item): bool => mb_strtolower((string) $item['content']) !== $content));

        if (count($remaining) === count($records)) {
            return;
        }

        $this->patch($zone, $remaining === []
            ? ['name' => self::absolute($record->name), 'type' => $record->type, 'changetype' => 'DELETE']
            : ['name' => self::absolute($record->name), 'type' => $record->type, 'ttl' => (int) ($rrset['ttl'] ?? self::AUTO_TTL), 'changetype' => 'REPLACE', 'records' => $remaining]);
    }

    private static function absolute(string $name): string
    {
        return mb_rtrim($name, '.').'.';
    }

    /** @return list<array{content: string, disabled: bool}> The records that already share the record's name and type. */
    private function records(Zone $zone, DnsRecord $record): array
    {
        return array_values(array_map(
            static fn (array $item): array => ['content' => (string) $item['content'], 'disabled' => (bool) ($item['disabled'] ?? false)],
            (array) ($this->rrset($zone, $record)['records'] ?? []),
        ));
    }

    /** @return array<string, mixed>|null */
    private function rrset(Zone $zone, DnsRecord $record): ?array
    {
        $name = mb_strtolower(self::absolute($record->name));
        $query = http_build_query(['rrsets' => 'true', 'rrset_name' => $name, 'rrset_type' => $record->type]);

        // Older PowerDNS versions ignore the filter and return every RRset, so it is filtered here too.
        foreach ((array) ($this->send('GET', '/zones/'.rawurlencode($zone->id).'?'.$query)['rrsets'] ?? []) as $rrset) {
            if (mb_strtolower((string) ($rrset['name'] ?? '')) === $name && ($rrset['type'] ?? null) === $record->type) {
                return $rrset;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $rrset */
    private function patch(Zone $zone, array $rrset): void
    {
        $this->send('PATCH', '/zones/'.rawurlencode($zone->id), ['rrsets' => [$rrset]]);
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<array-key, mixed>
     */
    private function send(string $method, string $path, ?array $json = null): array
    {
        $response = $this->http->send($method, $this->base.$path, ['X-API-Key' => $this->key], $json);

        if ($response->ok()) {
            return $response->body ?? [];
        }

        throw new DnsException('PowerDNS: '.match (true) {
            $response->status === 401 || $response->status === 403 => 'the API key was refused.',
            isset($response->body['error']) => "{$response->body['error']} (HTTP {$response->status})",
            default => "HTTP {$response->status}",
        });
    }
}
