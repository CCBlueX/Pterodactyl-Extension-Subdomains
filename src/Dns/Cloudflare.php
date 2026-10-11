<?php

declare(strict_types=1);

namespace Subdomains\Dns;

final readonly class Cloudflare implements Provider
{
    private const string API = 'https://api.cloudflare.com/client/v4';

    private const int RECORD_NOT_FOUND = 81044;

    public function __construct(private Transport $http, private string $token) {}

    /** @return array<string, mixed> The body Cloudflare expects for a record. */
    public static function payload(DnsRecord $record): array
    {
        $payload = ['type' => $record->type, 'name' => $record->name, 'ttl' => $record->proxied ? DnsRecord::AUTO_TTL : $record->ttl];

        if ($record->type === 'SRV') {
            $payload['data'] = ['priority' => $record->priority, 'weight' => $record->weight, 'port' => $record->port, 'target' => $record->content];
        } else {
            $payload['content'] = $record->content;
            $payload['proxied'] = $record->proxied;
        }

        if ($record->comment !== '') {
            $payload['comment'] = $record->comment;
        }

        return $payload;
    }

    public function findZone(string $domain): Zone
    {
        foreach (Zone::candidates($domain) as $name) {
            $zones = $this->send('GET', '/zones?'.http_build_query(['name' => $name]));

            if (isset($zones[0]['id'])) {
                return new Zone((string) $zones[0]['id'], $name);
            }
        }

        throw new DnsException("Cloudflare has no zone for {$domain} that this API token can access.");
    }

    public function create(Zone $zone, DnsRecord $record): DnsRecord
    {
        return $record->withId((string) ($this->send('POST', "/zones/{$zone->id}/dns_records", self::payload($record))['id'] ?? ''));
    }

    public function delete(Zone $zone, DnsRecord $record): void
    {
        if ($record->id === null) {
            return;
        }

        try {
            $this->send('DELETE', "/zones/{$zone->id}/dns_records/{$record->id}");
        } catch (DnsException $exception) {
            if ($exception->getCode() !== self::RECORD_NOT_FOUND) {
                throw $exception;
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<array-key, mixed>
     */
    private function send(string $method, string $path, ?array $json = null): array
    {
        $response = $this->http->send($method, self::API.$path, ['Authorization' => "Bearer {$this->token}"], $json);

        if (! $response->ok() || ($response->body['success'] ?? false) !== true) {
            $error = $response->body['errors'][0] ?? null;

            throw new DnsException(
                'Cloudflare: '.(is_array($error) ? "{$error['message']} ({$error['code']})" : "HTTP {$response->status}"),
                is_array($error) ? (int) $error['code'] : 0,
            );
        }

        return (array) ($response->body['result'] ?? []);
    }
}
