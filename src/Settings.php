<?php

declare(strict_types=1);

namespace Subdomains;

use Pterodactyl\Services\Extensions\ExtensionRepository;
use Pterodactyl\Services\Extensions\ExtensionSettingsRegistry;

final readonly class Settings
{
    private const string ID = 'subdomains';

    public function __construct(private ExtensionSettingsRegistry $registry, private ExtensionRepository $extensions) {}

    public function defaultLimit(): int
    {
        return (int) ($this->get('default_limit') ?? 1);
    }

    /** @return list<string> */
    public function reservedNames(): array
    {
        return array_values(array_map('strval', (array) ($this->get('blacklist') ?? [])));
    }

    /** @return list<string> */
    public function reservedPatterns(): array
    {
        return array_values(array_map('strval', (array) ($this->get('blacklist_patterns') ?? [])));
    }

    /** The token from 1.0, used by Cloudflare domains without their own. */
    public function cloudflareToken(): string
    {
        return (string) ($this->get('cloudflare_token') ?? '');
    }

    public function allowPrivateTargets(): bool
    {
        return (bool) ($this->get('allow_private_targets') ?? false);
    }

    /** @return list<int> Eggs that may use subdomains; empty means every egg. */
    public function allowedEggs(): array
    {
        return array_values(array_map('intval', (array) ($this->extensions->settings(self::ID)->get('allowed_eggs') ?? [])));
    }

    /** @param list<int> $eggs */
    public function setAllowedEggs(array $eggs): void
    {
        $this->extensions->settings(self::ID)->set('allowed_eggs', array_values(array_unique($eggs)));
    }

    public function allowsEgg(int $egg): bool
    {
        $allowed = $this->allowedEggs();

        return $allowed === [] || in_array($egg, $allowed, true);
    }

    private function get(string $key): mixed
    {
        return $this->registry->get(self::ID)?->get($key);
    }
}
