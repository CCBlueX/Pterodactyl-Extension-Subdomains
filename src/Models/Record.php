<?php

declare(strict_types=1);

namespace Subdomains\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pterodactyl\Models\Server;
use Subdomains\Dns\DnsRecord;

/**
 * A subdomain and the DNS records published for it.
 *
 * @property int $id
 * @property int $domain_id
 * @property int|null $server_id
 * @property string $server_uuid
 * @property int|null $allocation_id null follows the server's primary allocation
 * @property string|null $fallback_from the allocation that was removed, when the subdomain fell back to the primary one
 * @property string $name
 * @property string|null $target
 * @property int|null $port
 * @property string|null $note
 * @property list<array<string, mixed>>|null $dns_records
 * @property string|null $error
 * @property Domain $domain
 * @property Server|null $server
 */
final class Record extends Model
{
    protected $table = 'ext_subdomains_records';

    protected $fillable = ['domain_id', 'server_id', 'server_uuid', 'allocation_id', 'fallback_from', 'name', 'target', 'port', 'note', 'dns_records', 'error'];

    protected function casts(): array
    {
        return ['dns_records' => 'array', 'port' => 'integer', 'allocation_id' => 'integer'];
    }

    /** @return BelongsTo<Domain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function hostname(): string
    {
        return "{$this->name}.{$this->domain->name}";
    }

    /** @return list<DnsRecord> */
    public function dnsRecords(): array
    {
        return array_values(array_map(static fn (array $data): DnsRecord => DnsRecord::fromArray($data), $this->dns_records ?? []));
    }

    /** @param list<DnsRecord> $records */
    public function storeDnsRecords(array $records): void
    {
        $this->update(['dns_records' => array_map(static fn (DnsRecord $record): array => $record->toArray(), array_values($records))]);
    }
}
