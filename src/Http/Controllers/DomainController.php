<?php

declare(strict_types=1);

namespace Subdomains\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Subdomains\Dns\Providers;
use Subdomains\Dns\Zone;
use Subdomains\Models\Domain;
use Subdomains\Models\Record;
use Subdomains\Records\Manager;

final class DomainController
{
    private const string NAME = 'regex:/^([a-z0-9-]+\.)+[a-z]{2,}$/i';

    public function __construct(private readonly Providers $providers, private readonly Manager $records, private readonly AdminController $admin) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', self::NAME, 'unique:ext_subdomains_domains,name'],
            'provider' => ['required', Rule::in(array_keys(Providers::DEFINITIONS))],
            'eggs' => ['nullable', 'array'],
            'eggs.*' => ['integer', 'exists:eggs,id'],
        ]);
        $name = mb_strtolower($data['name']);
        $credentials = $this->credentials($request, $data['provider']);
        $zone = $this->zone($data['provider'], $name, $credentials);

        Domain::query()->create([
            'name' => $name,
            'provider' => $data['provider'],
            'zone_id' => $zone->id,
            'zone_name' => $zone->name,
            'credentials' => $credentials,
            'eggs' => self::eggs($data['eggs'] ?? []),
        ]);

        return $this->admin->index();
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $domain = Domain::query()->findOrFail($id);
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'eggs' => ['sometimes', 'nullable', 'array'],
            'eggs.*' => ['integer', 'exists:eggs,id'],
            'credentials' => ['sometimes', 'array'],
        ]);

        if (array_key_exists('credentials', $data)) {
            $credentials = $this->credentials($request, $domain->provider, $domain);
            $zone = $this->zone($domain->provider, $domain->name, $credentials);

            // Records already published live in the old zone, so the zone may only change while there are none.
            abort_if($zone->id !== $domain->zone_id && Record::query()->where('domain_id', $domain->id)->exists(), 422, "These credentials reach a different zone ({$zone->name}). Delete the domain's subdomains first.");
            $domain->fill(['credentials' => $credentials, 'zone_id' => $zone->id, 'zone_name' => $zone->name]);
        }

        if (array_key_exists('eggs', $data)) {
            $domain->eggs = self::eggs($data['eggs'] ?? []);
        }

        if (array_key_exists('enabled', $data)) {
            $domain->enabled = (bool) $data['enabled'];
        }

        $domain->save();

        return $this->admin->index();
    }

    /** Looks up the zone with the entered credentials; empty secret fields use the saved ones. */
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:191', self::NAME],
            'provider' => ['required', Rule::in(array_keys(Providers::DEFINITIONS))],
        ]);
        $existing = isset($data['id']) ? Domain::query()->findOrFail($data['id']) : null;
        $zone = $this->zone($data['provider'], mb_strtolower($data['name']), $this->credentials($request, $data['provider'], $existing));

        return new JsonResponse(['zone' => $zone->name, 'message' => "Connected. Records go into the zone {$zone->name}."]);
    }

    public function destroy(int $id): JsonResponse
    {
        $domain = Domain::query()->findOrFail($id);

        try {
            Record::query()->with('domain')->where('domain_id', $domain->id)->each(fn (Record $record) => $this->records->delete($record));
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        $domain->delete();

        return $this->admin->index();
    }

    /** @param list<int|string> $eggs */
    private static function eggs(array $eggs): ?array
    {
        $eggs = array_values(array_unique(array_map('intval', $eggs)));

        return $eggs === [] ? null : $eggs;
    }

    /**
     * Empty fields keep the saved value when the provider stays the same, so secrets never travel back to the browser.
     *
     * @return array<string, string>
     */
    private function credentials(Request $request, string $provider, ?Domain $existing = null): array
    {
        $input = array_filter(array_map(static fn ($value): string => is_string($value) ? mb_trim($value) : '', (array) $request->input('credentials', [])), static fn (string $value): bool => $value !== '');
        $credentials = $existing?->provider === $provider ? array_merge($existing->credentials ?? [], $input) : $input;
        $allowed = array_column(Providers::DEFINITIONS[$provider]['fields'], 'key');

        validator(['credentials' => $credentials], Providers::rules($provider), [], Providers::attributes($provider))->validate();

        return array_intersect_key($credentials, array_flip($allowed));
    }

    /** @param array<string, string> $credentials */
    private function zone(string $provider, string $name, array $credentials): Zone
    {
        try {
            return $this->providers->make($provider, $credentials)->findZone($name);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }
    }
}
