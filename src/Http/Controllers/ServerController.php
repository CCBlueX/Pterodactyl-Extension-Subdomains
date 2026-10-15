<?php

declare(strict_types=1);

namespace Subdomains\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use RuntimeException;
use Subdomains\Models\Domain;
use Subdomains\Models\Record;
use Subdomains\Records\Manager;

final class ServerController
{
    public function __construct(private readonly Manager $records) {}

    public function index(Request $request, Server $server): JsonResponse
    {
        $this->authorize($request, $server, 'read');

        return $this->response($server);
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $this->authorize($request, $server, 'create');

        $data = $request->validate([
            'domain' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:63'],
            'allocation' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $domain = Domain::query()->where('enabled', true)->findOrFail($data['domain']);

        // Pattern and random domains build the name themselves, so a name sent for them is refused.
        $this->attempt(fn () => $this->records->create($server, $domain, self::text($data['name'] ?? null), $data['allocation'] ?? null, self::text($data['note'] ?? null)));

        return $this->response($server);
    }

    public function update(Request $request, Server $server, int $id): JsonResponse
    {
        $this->authorize($request, $server, 'update');

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:63'],
            'allocation' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $record = Record::query()->with('domain')->where('server_id', $server->id)->findOrFail($id);

        $this->attempt(fn () => $this->records->update($record, $server, $data['allocation'] ?? null, self::text($data['note'] ?? null), self::text($data['name'] ?? null)));

        return $this->response($server);
    }

    /** Rolls a new random name for the next subdomain on a domain. */
    public function newName(Request $request, Server $server, int $id): JsonResponse
    {
        $this->authorize($request, $server, 'create');

        $domain = $this->records->domainsFor($server)->firstWhere('id', $id);
        abort_if($domain === null, 404, 'That domain is not available for this server.');
        abort_unless($domain->naming()->isRandom(), 422, "Names on {$domain->name} are not random.");

        $this->attempt(fn () => $this->records->suggestedName($server, $domain, fresh: true));

        return $this->response($server);
    }

    public function destroy(Request $request, Server $server, int $id): JsonResponse
    {
        $this->authorize($request, $server, 'delete');

        $record = Record::query()->with('domain')->where('server_id', $server->id)->findOrFail($id);
        $this->attempt(fn () => $this->records->delete($record));

        return $this->response($server);
    }

    private static function text(?string $text): ?string
    {
        $text = mb_trim((string) $text);

        return $text === '' ? null : $text;
    }

    private function response(Server $server): JsonResponse
    {
        $server->unsetRelation('allocations');

        return new JsonResponse([
            'records' => $this->records->records($server)->map(static fn (Record $record): array => Present::record($record, $server))->values(),
            'domains' => $this->records->domainsFor($server)->map(fn (Domain $domain): array => $this->domain($server, $domain))->values(),
            'allocations' => Present::allocations($server),
            'limit' => $this->records->limit($server),
        ]);
    }

    /** @return array{id: int, name: string, naming: string, random: bool, preview: string|null} */
    private function domain(Server $server, Domain $domain): array
    {
        $naming = $domain->naming();

        return [
            'id' => $domain->id,
            'name' => $domain->name,
            'naming' => $naming->mode,
            'random' => $naming->isRandom(),
            'preview' => $naming->isAutomatic() ? $this->preview($server, $domain) : null,
        ];
    }

    /** The name a new subdomain would get, or null when none is free. */
    private function preview(Server $server, Domain $domain): ?string
    {
        try {
            return $this->records->suggestedName($server, $domain);
        } catch (RuntimeException) {
            return null;
        }
    }

    private function attempt(callable $action): void
    {
        try {
            $action();
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }
    }

    /** "manage" from 1.0 still allows creating, changing and deleting. */
    private function authorize(Request $request, Server $server, string $permission): void
    {
        $user = $request->user();
        $allowed = (bool) $user?->can("ext.subdomains.{$permission}", $server) || ($permission !== 'read' && (bool) $user?->can('ext.subdomains.manage', $server));

        abort_unless($allowed, 403, 'You do not have permission to do that.');
    }
}
