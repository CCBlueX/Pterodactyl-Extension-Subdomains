<?php

declare(strict_types=1);

namespace Subdomains\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The public addresses of a node, for nodes whose allocations listen on 0.0.0.0 or private addresses.
 *
 * @property int $node_id
 * @property string|null $ipv4
 * @property string|null $ipv6
 */
final class NodeTarget extends Model
{
    public $incrementing = false;

    protected $table = 'ext_subdomains_nodes';

    protected $primaryKey = 'node_id';

    protected $fillable = ['node_id', 'ipv4', 'ipv6'];
}
