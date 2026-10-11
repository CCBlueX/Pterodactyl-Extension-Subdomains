<?php

declare(strict_types=1);

namespace Subdomains\Records;

use Illuminate\Support\Collection;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Server;
use RuntimeException;
use Subdomains\Dns\DnsRecord;
use Subdomains\Dns\Providers;
use Subdomains\Models\Domain;
use Subdomains\Models\Limit;
use Subdomains\Models\NodeTarget;
use Subdomains\Models\Record;
use Subdomains\Settings;
use Subdomains\Support\Blacklist;
use Subdomains\Support\Names;
use Subdomains\Support\Target;
use Throwable;

final readonly class Manager
{
    public function __construct(private Providers $providers, private Templates $templates, private Settings $settings) {}

    public function limit(Server $server): int
    {
        return Limit::query()->find($server->id)->limit ?? $this->settings->defaultLimit();
    }

    /** @return Collection<int, Record> */
    public function records(Server $server): Collection
    {
        return Record::query()->with('domain')->where('server_id', $server->id)->orderBy('name')->get();
    }

    /** @return Collection<int, Domain> The domains the server's egg may use. */
    public function domainsFor(Server $server): Collection
    {
        if (! $this->settings->allowsEgg($server->egg_id)) {
            return new Collection;
        }

        return Domain::query()->where('enabled', true)->orderBy('name')->get()->filter(fn (Domain $domain): bool => $domain->allowsEgg($server->egg_id))->values();
    }

    public function create(Server $server, Domain $domain, string $name, ?int $allocation = null, ?string $note = null): Record
    {
        $name = mb_strtolower(mb_trim($name));

        match (true) {
            ! $this->domainsFor($server)->contains('id', $domain->id) => throw new RuntimeException('That domain is not available for this server.'),
            ! Names::isValidLabel($name) => throw new RuntimeException('Use lowercase letters, numbers and dashes only.'),
            ($reason = Blacklist::reason($name, $this->settings->reservedNames(), $this->settings->reservedPatterns())) !== null => throw new RuntimeException($reason),
            Record::query()->where('domain_id', $domain->id)->where('name', $name)->exists() => throw new RuntimeException("{$name}.{$domain->name} is already taken."),
            $this->records($server)->count() >= $this->limit($server) => throw new RuntimeException('This server has reached its subdomain limit.'),
            default => $this->checkAllocation($server, $allocation),
        };

        $record = Record::query()->create([
            'domain_id' => $domain->id,
            'server_id' => $server->id,
            'server_uuid' => $server->uuid,
            'allocation_id' => $allocation,
            'name' => $name,
            'note' => $note,
        ]);
        $record->setRelation('domain', $domain);

        try {
            $this->sync($record, $server);
        } catch (Throwable $exception) {
            try {
                $this->delete($record);
            } catch (Throwable) {
                // The subdomain stays with its error, so it can be deleted again later.
            }

            throw $exception;
        }

        return $record;
    }

    /** Points the subdomain at another allocation (null for the primary one) and updates DNS right away. */
    public function update(Record $record, Server $server, ?int $allocation, ?string $note): void
    {
        $this->checkAllocation($server, $allocation);
        $record->update(['allocation_id' => $allocation, 'fallback_from' => null, 'note' => $note]);
        $this->sync($record, $server);
    }

    public function delete(Record $record): void
    {
        $remaining = $record->dnsRecords();

        if ($remaining !== []) {
            $provider = $this->providers->for($record->domain);

            foreach ($record->dnsRecords() as $index => $dns) {
                $provider->delete($record->domain->zone(), $dns);
                unset($remaining[$index]);
                $record->storeDnsRecords($remaining);
            }
        }

        $record->delete();
    }

    /** Removes records of deleted servers and brings the others in line with their server. */
    public function reconcile(Record $record): void
    {
        $record->server === null ? $this->delete($record) : $this->sync($record, $record->server);
    }

    /**
     * Publishes the records the subdomain should have and removes the ones it should not. Unchanged records stay,
     * unless $force recreates everything.
     */
    public function sync(Record $record, Server $server, bool $force = false): void
    {
        try {
            $this->fallBackWhenAllocationIsGone($record, $server);
            [$desired, $target] = $this->plan($record, $server);
            $stored = $record->dnsRecords();
            [$delete, $create] = $force ? [$stored, $desired] : DnsRecord::diff($stored, $desired);

            if ($delete !== [] || $create !== []) {
                $this->apply($record, $stored, $delete, $create);
            }

            $record->update(['target' => $target->address(), 'port' => $target->port, 'error' => null]);
        } catch (Throwable $exception) {
            $record->update(['error' => mb_substr($exception->getMessage(), 0, 500)]);

            throw $exception;
        }
    }

    /** The allocation the subdomain points to: the chosen one while the server still has it, else the primary one. */
    public function allocation(Record $record, Server $server): Allocation
    {
        return ($record->allocation_id === null ? null : $server->allocations->firstWhere('id', $record->allocation_id)) ?? $server->allocation;
    }

    /**
     * @param  list<DnsRecord>  $stored
     * @param  list<DnsRecord>  $delete
     * @param  list<DnsRecord>  $create
     */
    private function apply(Record $record, array $stored, array $delete, array $create): void
    {
        $provider = $this->providers->for($record->domain);
        $zone = $record->domain->zone();
        $kept = array_values(array_filter($stored, static fn (DnsRecord $dns): bool => ! in_array($dns, $delete, true)));

        // Deleting first lets a CNAME replace A records of the same name.
        foreach ($delete as $index => $dns) {
            $provider->delete($zone, $dns);
            $record->storeDnsRecords([...$kept, ...array_slice($delete, $index + 1)]);
        }

        foreach ($create as $dns) {
            $kept[] = $provider->create($zone, $dns);
            $record->storeDnsRecords($kept);
        }
    }

    /** @return array{0: list<DnsRecord>, 1: Target} */
    private function plan(Record $record, Server $server): array
    {
        $allocation = $this->allocation($record, $server);
        $node = NodeTarget::query()->find($server->node_id)?->settings() ?? [];
        $target = Target::for($node, (string) $server->node->fqdn, (string) $allocation->ip, $allocation->ip_alias, (int) $allocation->port, static fn (string $host): string => gethostbyname($host));

        return [$this->templates->forEgg($server->egg)->records($target, $record->name, $record->hostname(), $server->name), $target];
    }

    private function fallBackWhenAllocationIsGone(Record $record, Server $server): void
    {
        if ($record->allocation_id === null || $server->allocations->contains('id', $record->allocation_id)) {
            return;
        }

        $gone = Allocation::query()->find($record->allocation_id);
        $record->update(['allocation_id' => null, 'fallback_from' => $gone === null ? 'an allocation' : "{$gone->ip}:{$gone->port}"]);
    }

    private function checkAllocation(Server $server, ?int $allocation): void
    {
        if ($allocation !== null && ! $server->allocations->contains('id', $allocation)) {
            throw new RuntimeException('That allocation does not belong to this server.');
        }
    }
}
