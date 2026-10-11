<?php

declare(strict_types=1);

namespace Subdomains\Dns;

/**
 * A DNS zone at a provider: the provider's id for it and its name, e.g. "example.com".
 */
final readonly class Zone
{
    public function __construct(public string $id, public string $name) {}

    /**
     * The zone names that can hold a domain, most specific first: "mc.example.com" can live in "mc.example.com" or "example.com".
     *
     * @return list<string>
     */
    public static function candidates(string $domain): array
    {
        $labels = explode('.', mb_strtolower(mb_trim($domain, '. ')));
        $names = [];

        for ($i = 0; $i <= count($labels) - 2; $i++) {
            $names[] = implode('.', array_slice($labels, $i));
        }

        return $names;
    }

    /** The name relative to the zone, "" for the zone itself: "play.example.com" in "example.com" is "play". */
    public function relative(string $name): string
    {
        $name = mb_strtolower(mb_rtrim($name, '.'));

        return $name === $this->name ? '' : (string) preg_replace('/\.'.preg_quote($this->name, '/').'$/', '', $name);
    }
}
