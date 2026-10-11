<?php

declare(strict_types=1);

namespace Subdomains\Models;

use Illuminate\Database\Eloquent\Model;
use Subdomains\Dns\Zone;

/**
 * A domain users create subdomains on. The credentials of its DNS provider are stored encrypted.
 *
 * @property int $id
 * @property string $name
 * @property string $provider
 * @property string $zone_id
 * @property string|null $zone_name
 * @property array<string, string>|null $credentials
 * @property list<int>|null $eggs
 * @property bool $enabled
 */
final class Domain extends Model
{
    protected $table = 'ext_subdomains_domains';

    protected $fillable = ['name', 'provider', 'zone_id', 'zone_name', 'credentials', 'eggs', 'enabled'];

    protected $hidden = ['credentials'];

    protected $attributes = ['enabled' => true, 'provider' => 'cloudflare'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'credentials' => 'encrypted:array', 'eggs' => 'array'];
    }

    public function zone(): Zone
    {
        return new Zone($this->zone_id, $this->zone_name ?? $this->name);
    }

    /** Domains without an egg list are open to every egg. */
    public function allowsEgg(int $egg): bool
    {
        return empty($this->eggs) || in_array($egg, array_map('intval', $this->eggs), true);
    }
}
