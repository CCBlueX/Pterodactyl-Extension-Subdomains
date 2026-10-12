<?php

declare(strict_types=1);

namespace Subdomains\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Subdomains\Dns\Providers;
use Subdomains\Dns\Zone;
use Subdomains\Models\Domain;
use Subdomains\Models\Record;
use Subdomains\Records\Manager;
use Subdomains\Support\Naming;

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
        $naming = $this->naming($request);
        $this->checkNotASubdomain($name);
        $credentials = $this->credentials($request, $data['provider']);
        $zone = $this->zone($data['provider'], $name, $credentials);

        Domain::query()->create([
            'name' => $name,
            'provider' => $data['provider'],
            'zone_id' => $zone->id,
            'zone_name' => $zone->name,
            'credentials' => $credentials,
            'eggs' => self::eggs($data['eggs'] ?? []),
        ] + $naming);

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
        $domain->fill($this->naming($request, $domain));

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

        $name = mb_strtolower($data['name']);
        $message = $zone->name === $name
            ? "Connected. Records go into the zone {$zone->name}."
            : "Connected. {$name} is a range in the zone {$zone->name}: users get names like play.{$name}.";

        return new JsonResponse(['zone' => $zone->name, 'message' => $message]);
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
     * The naming fields, with the domain's current values for fields that were not sent. The pattern is only checked
     * and saved when the domain uses it.
     *
     * @return array{naming_mode: string, naming_pattern: string|null, naming_length: int}
     */
    private function naming(Request $request, ?Domain $domain = null): array
    {
        $data = $request->validate([
            'naming_mode' => ['sometimes', Rule::in(Naming::MODES)],
            'naming_pattern' => ['sometimes', 'nullable', 'string', 'max:'.Naming::MAX_PATTERN],
            'naming_length' => ['sometimes', 'integer', 'between:'.Naming::MIN_LENGTH.','.Naming::MAX_LENGTH],
        ], [], ['naming_mode' => 'names', 'naming_pattern' => 'pattern', 'naming_length' => 'number of random characters']);
        $mode = $data['naming_mode'] ?? $domain?->naming_mode ?? Naming::CUSTOM;
        $pattern = $domain?->naming_pattern;

        if ($mode === Naming::PATTERN) {
            $pattern = mb_strtolower(mb_trim((string) ($data['naming_pattern'] ?? $pattern ?? ''))) ?: Naming::DEFAULT_PATTERN;

            if (($problem = Naming::patternProblem($pattern)) !== null) {
                throw ValidationException::withMessages(['naming_pattern' => $problem]);
            }
        }

        return ['naming_mode' => $mode, 'naming_pattern' => $pattern, 'naming_length' => (int) ($data['naming_length'] ?? $domain?->naming_length ?? Naming::DEFAULT_LENGTH)];
    }

    /** A range like serv.example.com cannot be added while a server already has it as its subdomain. */
    private function checkNotASubdomain(string $name): void
    {
        [$label, $parent] = explode('.', $name, 2);
        $taken = Record::query()->where('name', $label)->whereHas('domain', static fn ($query) => $query->where('name', $parent))->exists();

        abort_if($taken, 422, "A server already uses {$name} as its subdomain. Delete that subdomain first to use {$name} as a range.");
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
