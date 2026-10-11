<?php

declare(strict_types=1);

namespace Subdomains\Dns;

final readonly class Bunny implements Provider
{
    private const string API = 'https://api.bunny.net';

    private const array TYPES = ['A' => 0, 'AAAA' => 1, 'CNAME' => 2, 'TXT' => 3, 'SRV' => 8];

    private const int AUTO_TTL = 300;

    public function __construct(private Transport $http, private string $key) {}

    /** @return array<string, mixed> The body Bunny expects for a record. Names are relative to the zone. */
    public static function payload(Zone $zone, DnsRecord $record): array
    {
        $payload = [
            'Type' => self::TYPES[$record->type] ?? throw new DnsException("Bunny DNS does not support {$record->type} records here."),
            'Name' => $zone->relative($record->name),
            'Value' => $record->content,
            'Ttl' => $record->ttl === DnsRecord::AUTO_TTL ? self::AUTO_TTL : $record->ttl,
        ];

        if ($record->type === 'SRV') {
            $payload += ['Priority' => $record->priority, 'Weight' => $record->weight, 'Port' => $record->port];
        }

        if ($record->comment !== '') {
            $payload['Comment'] = $record->comment;
        }

        return $payload;
    }

    public function findZone(string $domain): Zone
    {
        foreach (Zone::candidates($domain) as $name) {
            $zones = $this->send('GET', '/dnszone?'.http_build_query(['search' => $name, 'perPage' => 1000]));

            foreach ((array) ($zones['Items'] ?? []) as $zone) {
                if (mb_strtolower((string) ($zone['Domain'] ?? '')) === $name) {
                    return new Zone((string) $zone['Id'], $name);
                }
            }
        }

        throw new DnsException("Bunny DNS has no zone for {$domain} in this account.");
    }

    public function create(Zone $zone, DnsRecord $record): DnsRecord
    {
        return $record->withId((string) ($this->send('PUT', "/dnszone/{$zone->id}/records", self::payload($zone, $record))['Id'] ?? ''));
    }

    public function delete(Zone $zone, DnsRecord $record): void
    {
        if ($record->id !== null) {
            $this->send('DELETE', "/dnszone/{$zone->id}/records/{$record->id}", allowMissing: true);
        }
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<array-key, mixed>
     */
    private function send(string $method, string $path, ?array $json = null, bool $allowMissing = false): array
    {
        $response = $this->http->send($method, self::API.$path, ['AccessKey' => $this->key], $json);

        if ($response->ok() || ($allowMissing && $response->status === 404)) {
            return $response->body ?? [];
        }

        throw new DnsException('Bunny DNS: '.match (true) {
            $response->status === 401 => 'the API key was refused.',
            isset($response->body['Message']) => "{$response->body['Message']} (HTTP {$response->status})",
            default => "HTTP {$response->status}",
        });
    }
}
