<?php

declare(strict_types=1);

namespace Subdomains\Http\Controllers;

use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Server;
use Subdomains\Dns\DnsRecord;
use Subdomains\Dns\Providers;
use Subdomains\Models\Domain;
use Subdomains\Models\Record;

final class Present
{
    /** @return array<string, mixed> */
    public static function record(Record $record, ?Server $server = null): array
    {
        $dns = $record->dnsRecords();
        $allocation = $server !== null && $record->allocation_id !== null ? $server->allocations->firstWhere('id', $record->allocation_id) : null;

        return [
            'id' => $record->id,
            'name' => $record->name,
            'hostname' => $record->hostname(),
            'domain' => $record->domain->name,
            'domain_id' => $record->domain_id,
            'target' => $record->target,
            'port' => $record->port,
            'allocation_id' => $record->allocation_id,
            'allocation' => $allocation instanceof Allocation ? self::allocationLabel($allocation) : null,
            'allocation_missing' => $server !== null && $record->allocation_id !== null && $allocation === null,
            'fallback_from' => $record->fallback_from,
            'note' => $record->note,
            'srv' => array_any($dns, static fn (DnsRecord $item): bool => $item->type === 'SRV'),
            'proxied' => array_any($dns, static fn (DnsRecord $item): bool => $item->proxied),
            'error' => $record->error,
            'created_at' => $record->created_at?->toAtomString(),
        ];
    }

    /** @return list<array{id: int, label: string, primary: bool}> */
    public static function allocations(Server $server): array
    {
        return $server->allocations
            ->sortBy(static fn (Allocation $allocation): string => ($allocation->id === $server->allocation_id ? '0' : '1').mb_str_pad((string) $allocation->port, 5, '0', STR_PAD_LEFT))
            ->map(static fn (Allocation $allocation): array => ['id' => $allocation->id, 'label' => self::allocationLabel($allocation), 'primary' => $allocation->id === $server->allocation_id])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> Credentials are reduced to plain fields and which secrets are set. */
    public static function domain(Domain $domain, int $records): array
    {
        $credentials = Providers::visible($domain->provider, $domain->credentials ?? []);
        $naming = $domain->naming();

        return [
            'id' => $domain->id,
            'name' => $domain->name,
            'provider' => $domain->provider,
            'zone' => $domain->zone_name ?? $domain->name,
            'enabled' => $domain->enabled,
            'eggs' => array_values(array_map('intval', $domain->eggs ?? [])),
            'naming_mode' => $naming->mode,
            'naming_pattern' => $naming->pattern,
            'naming_length' => $naming->length,
            'records' => $records,
            'credentials' => $credentials['values'],
            'secrets' => $credentials['secrets'],
        ];
    }

    private static function allocationLabel(Allocation $allocation): string
    {
        return ($allocation->ip_alias ?: $allocation->ip).':'.$allocation->port;
    }
}
