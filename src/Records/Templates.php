<?php

declare(strict_types=1);

namespace Subdomains\Records;

use Pterodactyl\Models\Egg;
use Subdomains\Models\RecordTemplate;
use Subdomains\Support\Template;

/**
 * Finds the template of an egg: its own override, else the Minecraft template for Minecraft eggs, else the default.
 */
final readonly class Templates
{
    public const string DEFAULT = 'default';

    public const string MINECRAFT = 'minecraft';

    public static function isMinecraft(Egg $egg): bool
    {
        $features = array_map('mb_strtolower', (array) ($egg->inherit_features ?? $egg->features ?? []));

        return in_array('eula', $features, true) || in_array('minecraft', $egg->tagSlugs(), true);
    }

    public static function eggKey(int $egg): string
    {
        return "egg.{$egg}";
    }

    /** The template an egg uses without an override. */
    public static function inheritedKey(Egg $egg): string
    {
        return self::isMinecraft($egg) ? self::MINECRAFT : self::DEFAULT;
    }

    public function forEgg(Egg $egg): Template
    {
        return $this->stored(self::eggKey($egg->id)) ?? $this->shared(self::inheritedKey($egg));
    }

    /** The default or Minecraft template, changed or built in. */
    public function shared(string $key): Template
    {
        return $this->stored($key) ?? ($key === self::MINECRAFT ? Template::minecraft() : Template::standard());
    }

    public function stored(string $key): ?Template
    {
        return RecordTemplate::query()->where('key', $key)->first()?->toTemplate();
    }
}
