<?php

declare(strict_types=1);

namespace Subdomains\Records;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\UniqueConstraintViolationException;
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
use Subdomains\Support\Target;
use Throwable;

final readonly class Manager
{
    /** How long a random name shown to a user stays reserved for them, in seconds. */
    private const int KEEP_NAME = 3600;

    private const int INSERT_ATTEMPTS = 3;

    public function __construct(private Providers $providers, private Templates $templates, private Settings $settings, private Cache $cache) {}

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

    /** $name is what the user typed on domains with typed names, and null on pattern and random domains. */
    public function create(Server $server, Domain $domain, ?string $name, ?int $allocation = null, ?string $note = null): Record
    {
        $automatic = $domain->naming()->isAutomatic();

        match (true) {
            ! $this->domainsFor($server)->contains('id', $domain->id) => throw new RuntimeException('That domain is not available for this server.'),
            $automatic && $name !== null => throw new RuntimeException("Names on {$domain->name} are picked automatically."),
            ! $automatic && $name === null => throw new RuntimeException('Enter a name.'),
            $this->records($server)->count() >= $this->limit($server) => throw new RuntimeException('This server has reached its subdomain limit.'),
            default => $this->checkAllocation($server, $allocation),
        };

        $record = $this->insert($server, $domain, $name, $allocation, $note);

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

    /**
     * The name the next subdomain of the server gets on a pattern or random domain. Random names are kept for a while,
     * so the name a user sees before creating is the one they get.
     */
    public function suggestedName(Server $server, Domain $domain, bool $fresh = false): string
    {
        $naming = $domain->naming();
        $values = ['username' => (string) $server->user?->username, 'server' => (string) $server->name, 'id' => (string) $server->uuidShort];
        $isFree = fn (string $name): bool => $this->unavailable($domain, $name) === null;

        if (! $naming->isRandom()) {
            return $naming->pick($values, $isFree);
        }

        $key = self::nameKey($server, $domain);
        $rule = hash('xxh128', (string) json_encode([$naming, $values]));
        $kept = $fresh ? null : $this->cache->get($key);

        if (is_array($kept) && ($kept['rule'] ?? null) === $rule && is_string($kept['name'] ?? null) && $isFree($kept['name'])) {
            return $kept['name'];
        }

        $name = $naming->pick($values, $isFree);
        $this->cache->put($key, ['rule' => $rule, 'name' => $name], self::KEEP_NAME);

        return $name;
    }

    /**
     * Points the subdomain at another allocation (null for the primary one), changes its note and, on domains with
     * typed names, renames it. DNS is updated right away.
     */
    public function update(Record $record, Server $server, ?int $allocation, ?string $note, ?string $name = null): void
    {
        $this->checkAllocation($server, $allocation);
        $previous = $record->name;
        $name = $name === null ? $previous : mb_strtolower(mb_trim($name));

        if ($name !== $previous) {
            match (true) {
                $record->domain->naming()->isAutomatic() => throw new RuntimeException("Names on {$record->domain->name} are picked automatically, so this subdomain cannot be renamed."),
                ! $this->domainsFor($server)->contains('id', $record->domain_id) => throw new RuntimeException('Its domain is no longer available for this server, so it cannot be renamed.'),
                default => $this->checkedName($record->domain, $name, $record->id),
            };
        }

        try {
            $record->update(['name' => $name, 'allocation_id' => $allocation, 'fallback_from' => null, 'note' => $note]);
        } catch (UniqueConstraintViolationException) {
            throw new RuntimeException("{$record->domain->naming()->hostname($name)} is already taken.");
        }

        try {
            $this->sync($record, $server);
        } catch (Throwable $exception) {
            if ($name !== $previous) {
                $this->restoreName($record, $server, $previous);
            }

            throw $exception;
        }
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

    private static function nameKey(Server $server, Domain $domain): string
    {
        return "ext.subdomains.name.{$server->id}.{$domain->id}";
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

    /** Inserts the subdomain. An automatic name someone else took in the meantime is picked again. */
    private function insert(Server $server, Domain $domain, ?string $name, ?int $allocation, ?string $note): Record
    {
        $automatic = $domain->naming()->isAutomatic();

        for ($attempt = 1; ; $attempt++) {
            $label = $automatic ? $this->suggestedName($server, $domain, fresh: $attempt > 1) : $this->checkedName($domain, (string) $name);

            try {
                $record = Record::query()->create([
                    'domain_id' => $domain->id,
                    'server_id' => $server->id,
                    'server_uuid' => $server->uuid,
                    'allocation_id' => $allocation,
                    'name' => $label,
                    'note' => $note,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                if (! $automatic || $attempt >= self::INSERT_ATTEMPTS) {
                    throw new RuntimeException("{$domain->naming()->hostname($label)} is already taken.", previous: $exception);
                }

                continue;
            }

            if ($automatic) {
                $this->cache->forget(self::nameKey($server, $domain));
            }

            return $record->setRelation('domain', $domain);
        }
    }

    /** The name a user typed, checked and lowercased. */
    private function checkedName(Domain $domain, string $name, ?int $except = null): string
    {
        $name = mb_strtolower(mb_trim($name));
        $reason = $domain->naming()->problem($name) ?? $this->unavailable($domain, $name, $except);

        return $reason === null ? $name : throw new RuntimeException($reason);
    }

    /**
     * Why the name cannot be taken on the domain, or null when it is free. A name that is itself a range, like "serv"
     * on example.com while serv.example.com is a domain, is reserved for the range.
     */
    private function unavailable(Domain $domain, string $name, ?int $except = null): ?string
    {
        $hostname = $domain->naming()->hostname($name);

        return match (true) {
            ($reason = Blacklist::reason($name, $this->settings->reservedNames(), $this->settings->reservedPatterns())) !== null => $reason,
            Record::query()->where('domain_id', $domain->id)->where('name', $name)->when($except !== null, static fn ($query) => $query->whereKeyNot($except))->exists() => "{$hostname} is already taken.",
            Domain::query()->where('name', $hostname)->exists() => "{$hostname} is reserved.",
            default => null,
        };
    }

    /** Puts the old name back after a rename DNS refused. */
    private function restoreName(Record $record, Server $server, string $name): void
    {
        $record->update(['name' => $name]);

        try {
            $this->sync($record, $server);
        } catch (Throwable) {
            // The subdomain keeps the error, so its owner sees what went wrong.
        }
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
