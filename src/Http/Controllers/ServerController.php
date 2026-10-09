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
        $this->authorize($request, $server, 'manage');

        $data = $request->validate([
            'domain' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:63'],
        ]);

        $domain = Domain::query()->where('enabled', true)->findOrFail($data['domain']);

        try {
            $this->records->create($server, $domain, $data['name']);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->response($server);
    }

    public function destroy(Request $request, Server $server, int $id): JsonResponse
    {
        $this->authorize($request, $server, 'manage');

        try {
            $this->records->delete(Record::query()->with('domain')->where('server_id', $server->id)->findOrFail($id));
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->response($server);
    }

    private function response(Server $server): JsonResponse
    {
        return new JsonResponse([
            'records' => $this->records->records($server)->map(static fn (Record $record): array => Present::record($record))->values(),
            'domains' => Domain::query()->where('enabled', true)->orderBy('name')->get(['id', 'name']),
            'limit' => $this->records->limit($server),
        ]);
    }

    private function authorize(Request $request, Server $server, string $permission): void
    {
        abort_unless((bool) $request->user()?->can("ext.subdomains.{$permission}", $server), 403, 'You do not have permission to do that.');
    }
}
