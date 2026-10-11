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
            'name' => ['required', 'string', 'max:63'],
            'allocation' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $domain = Domain::query()->where('enabled', true)->findOrFail($data['domain']);

        $this->attempt(fn () => $this->records->create($server, $domain, $data['name'], $data['allocation'] ?? null, self::note($data['note'] ?? null)));

        return $this->response($server);
    }

    public function update(Request $request, Server $server, int $id): JsonResponse
    {
        $this->authorize($request, $server, 'update');

        $data = $request->validate([
            'allocation' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $record = Record::query()->with('domain')->where('server_id', $server->id)->findOrFail($id);

        $this->attempt(fn () => $this->records->update($record, $server, $data['allocation'] ?? null, self::note($data['note'] ?? null)));

        return $this->response($server);
    }

    public function destroy(Request $request, Server $server, int $id): JsonResponse
    {
        $this->authorize($request, $server, 'delete');

        $record = Record::query()->with('domain')->where('server_id', $server->id)->findOrFail($id);
        $this->attempt(fn () => $this->records->delete($record));

        return $this->response($server);
    }

    private static function note(?string $note): ?string
    {
        $note = mb_trim((string) $note);

        return $note === '' ? null : $note;
    }

    private function response(Server $server): JsonResponse
    {
        $server->unsetRelation('allocations');

        return new JsonResponse([
            'records' => $this->records->records($server)->map(static fn (Record $record): array => Present::record($record, $server))->values(),
            'domains' => $this->records->domainsFor($server)->map(static fn (Domain $domain): array => ['id' => $domain->id, 'name' => $domain->name])->values(),
            'allocations' => Present::allocations($server),
            'limit' => $this->records->limit($server),
        ]);
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
