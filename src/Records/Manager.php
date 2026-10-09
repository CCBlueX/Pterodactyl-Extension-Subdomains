<?php

declare(strict_types=1);

namespace Subdomains\Records;

use Illuminate\Support\Collection;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Extensions\ExtensionSettingsRegistry;
use RuntimeException;
use Subdomains\Cloudflare\Client;
use Subdomains\Models\Domain;
use Subdomains\Models\Limit;
use Subdomains\Models\NodeTarget;
use Subdomains\Models\Record;
use Subdomains\Support\RecordPlan;
use Throwable;

final readonly class Manager
{
    public function __construct(private Client $cloudflare, private ExtensionSettingsRegistry $settings) {}

    public function limit(Server $server): int
    {
        return Limit::query()->find($server->id)->limit ?? (int) ($this->settings->get('subdomains')?->get('default_limit') ?? 1);
    }

    /** @return Collection<int, Record> */
    public function records(Server $server): Collection
    {
        return Record::query()->with('domain')->where('server_id', $server->id)->orderBy('name')->get();
    }

    public function create(Server $server, Domain $domain, string $name): Record
    {
        $name = mb_strtolower(mb_trim($name));
        $blocked = array_map('mb_strtolower', (array) ($this->settings->get('subdomains')?->get('blacklist') ?? []));

        match (true) {
            ! $domain->enabled => throw new RuntimeException('That domain is not available.'),
            ! RecordPlan::isValidName($name) => throw new RuntimeException('Use lowercase letters, numbers and dashes only.'),
            in_array($name, $blocked, true) => throw new RuntimeException("\"{$name}\" is reserved."),
            Record::query()->where('domain_id', $domain->id)->where('name', $name)->exists() => throw new RuntimeException("{$name}.{$domain->name} is already taken."),
            $this->records($server)->count() >= $this->limit($server) => throw new RuntimeException('This server has reached its subdomain limit.'),
            default => null,
        };

        $record = Record::query()->create(['domain_id' => $domain->id, 'server_id' => $server->id, 'server_uuid' => $server->uuid, 'name' => $name]);

        try {
            $this->publish($record->setRelation('domain', $domain), $server);
        } catch (Throwable $exception) {
            $record->delete();

            throw $exception;
        }

        return $record;
    }

    public function delete(Record $record): void
    {
        foreach ($record->cloudflare_ids ?? [] as $id) {
            $this->cloudflare->delete($record->domain->zone_id, $id);
        }

        $record->delete();
    }

    /** Recreates the DNS records when the server's address or port changed. */
    public function sync(Record $record, Server $server, bool $force = false): void
    {
        [$ipv4, , $port] = $this->target($server);

        if (! $force && $record->target === $ipv4 && $record->port === $port && $record->error === null) {
            return;
        }

        foreach ($record->cloudflare_ids ?? [] as $id) {
            $this->cloudflare->delete($record->domain->zone_id, $id);
        }

        $record->update(['cloudflare_ids' => null]);

        try {
            $this->publish($record, $server);
        } catch (Throwable $exception) {
            $record->update(['error' => mb_substr($exception->getMessage(), 0, 500)]);

            throw $exception;
        }
    }

    private function publish(Record $record, Server $server): void
    {
        [$ipv4, $ipv6, $port, $minecraft] = $this->target($server);

        if ($ipv4 === null && $ipv6 === null) {
            throw new RuntimeException('The server has no public address. An administrator can set one for its node.');
        }

        $ids = [];

        foreach (RecordPlan::for($record->hostname(), $ipv4, $ipv6, $port, $minecraft) as $kind => $payload) {
            $ids[$kind] = $this->cloudflare->create($record->domain->zone_id, $payload);
            $record->update(['cloudflare_ids' => $ids]);
        }

        $record->update(['target' => $ipv4 ?? $ipv6, 'port' => $port, 'error' => null]);
    }

    /** @return array{0: string|null, 1: string|null, 2: int, 3: bool} */
    private function target(Server $server): array
    {
        $allocation = $server->allocation;
        $node = NodeTarget::query()->find($server->node_id);
        $features = array_map('mb_strtolower', (array) ($server->egg->inherit_features ?? $server->egg->features ?? []));
        $minecraft = in_array('eula', $features, true) || in_array('minecraft', $server->egg->tagSlugs(), true);

        $ipv4 = $node?->ipv4
            ?: RecordPlan::publicIpv4($allocation->ip)
            ?: RecordPlan::publicIpv4($allocation->ip_alias)
            ?: RecordPlan::publicIpv4(gethostbyname($server->node->fqdn));

        return [$ipv4, $node?->ipv6 ?: null, (int) $allocation->port, $minecraft];
    }
}
