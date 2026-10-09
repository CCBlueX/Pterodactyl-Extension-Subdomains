<?php

declare(strict_types=1);

namespace Subdomains\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pterodactyl\Models\Server;

/**
 * @property int $id
 * @property int $domain_id
 * @property int|null $server_id
 * @property string $server_uuid
 * @property string $name
 * @property string|null $target
 * @property int|null $port
 * @property array<string, string>|null $cloudflare_ids
 * @property string|null $error
 * @property Domain $domain
 * @property Server|null $server
 */
final class Record extends Model
{
    protected $table = 'ext_subdomains_records';

    protected $fillable = ['domain_id', 'server_id', 'server_uuid', 'name', 'target', 'port', 'cloudflare_ids', 'error'];

    protected function casts(): array
    {
        return ['cloudflare_ids' => 'array', 'port' => 'integer'];
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
}
