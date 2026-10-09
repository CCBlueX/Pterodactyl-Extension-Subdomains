<?php

declare(strict_types=1);

namespace Subdomains\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $zone_id
 * @property bool $enabled
 */
final class Domain extends Model
{
    protected $table = 'ext_subdomains_domains';

    protected $fillable = ['name', 'zone_id', 'enabled'];

    protected $attributes = ['enabled' => true];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
