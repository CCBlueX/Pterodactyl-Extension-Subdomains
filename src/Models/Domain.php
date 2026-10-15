<?php

declare(strict_types=1);

namespace Subdomains\Models;

use Illuminate\Database\Eloquent\Model;
use Subdomains\Dns\Zone;
use Subdomains\Support\Naming;

/**
 * A domain or range users create subdomains on, e.g. "example.com" or "serv.example.com" in the zone "example.com".
 * The credentials of its DNS provider are stored encrypted.
 *
 * @property int $id
 * @property string $name
 * @property string $provider
 * @property string $zone_id
 * @property string|null $zone_name
 * @property array<string, string>|null $credentials
 * @property list<int>|null $eggs
 * @property string $naming_mode
 * @property string|null $naming_pattern
 * @property int $naming_length
 * @property bool $enabled
 */
final class Domain extends Model
{
    protected $table = 'ext_subdomains_domains';

    protected $fillable = ['name', 'provider', 'zone_id', 'zone_name', 'credentials', 'eggs', 'naming_mode', 'naming_pattern', 'naming_length', 'enabled'];

    protected $hidden = ['credentials'];

    protected $attributes = ['enabled' => true, 'provider' => 'cloudflare', 'naming_mode' => Naming::CUSTOM, 'naming_length' => Naming::DEFAULT_LENGTH];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'credentials' => 'encrypted:array', 'eggs' => 'array', 'naming_length' => 'integer'];
    }

    public function naming(): Naming
    {
        return new Naming($this->name, (string) $this->naming_mode, $this->naming_pattern, (int) ($this->naming_length ?? Naming::DEFAULT_LENGTH));
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
