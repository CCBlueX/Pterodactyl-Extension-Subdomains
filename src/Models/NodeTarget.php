<?php

declare(strict_types=1);

namespace Subdomains\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Where subdomains on a node point: its public addresses ("ip"), the allocation alias ("alias") or a hostname ("hostname").
 *
 * @property int $node_id
 * @property string $mode
 * @property string|null $ipv4
 * @property string|null $ipv6
 * @property string|null $hostname
 */
final class NodeTarget extends Model
{
    public $incrementing = false;

    protected $table = 'ext_subdomains_nodes';

    protected $primaryKey = 'node_id';

    protected $fillable = ['node_id', 'mode', 'ipv4', 'ipv6', 'hostname'];

    protected $attributes = ['mode' => 'ip'];

    /** @return array{mode: string, ipv4: string|null, ipv6: string|null, hostname: string|null} */
    public function settings(): array
    {
        return ['mode' => $this->mode, 'ipv4' => $this->ipv4, 'ipv6' => $this->ipv6, 'hostname' => $this->hostname];
    }
}
