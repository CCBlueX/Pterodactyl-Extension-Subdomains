<?php

declare(strict_types=1);

namespace Subdomains\Models;

use Illuminate\Database\Eloquent\Model;
use Subdomains\Support\Template;

/**
 * A template an administrator changed: "default", "minecraft" or "egg.<id>" for a per-egg override.
 *
 * @property int $id
 * @property string $key
 * @property int|null $egg_id
 * @property array<string, mixed> $template
 */
final class RecordTemplate extends Model
{
    protected $table = 'ext_subdomains_templates';

    protected $fillable = ['key', 'egg_id', 'template'];

    protected function casts(): array
    {
        return ['template' => 'array', 'egg_id' => 'integer'];
    }

    public function toTemplate(): Template
    {
        return Template::fromArray($this->template);
    }
}
