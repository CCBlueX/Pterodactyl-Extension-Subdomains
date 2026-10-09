<?php

declare(strict_types=1);

namespace Subdomains\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $server_id
 * @property int $limit
 */
final class Limit extends Model
{
    public $incrementing = false;

    protected $table = 'ext_subdomains_limits';

    protected $primaryKey = 'server_id';

    protected $fillable = ['server_id', 'limit'];
}
