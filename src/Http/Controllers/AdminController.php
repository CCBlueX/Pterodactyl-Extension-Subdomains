<?php

declare(strict_types=1);

namespace Subdomains\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use RuntimeException;
use Subdomains\Cloudflare\Client;
use Subdomains\Models\Domain;
use Subdomains\Models\Limit;
use Subdomains\Models\NodeTarget;
use Subdomains\Models\Record;
use Subdomains\Records\Manager;
use Throwable;

final class AdminController
{
    public function __construct(private readonly Client $cloudflare, private readonly Manager $records) {}

    public function index(): JsonResponse
    {
        $targets = NodeTarget::query()->get()->keyBy('node_id');

        return new JsonResponse([
            'domains' => Domain::query()->orderBy('name')->get()->map(static fn (Domain $domain): array => [
                'id' => $domain->id,
                'name' => $domain->name,
                'enabled' => $domain->enabled,
                'records' => Record::query()->where('domain_id', $domain->id)->count(),
            ]),
            'nodes' => Node::query()->orderBy('name')->get(['id', 'name', 'fqdn'])->map(static fn (Node $node): array => [
                'id' => $node->id,
                'name' => $node->name,
                'fqdn' => $node->fqdn,
                'ipv4' => $targets->get($node->id)?->ipv4,
                'ipv6' => $targets->get($node->id)?->ipv6,
            ]),
        ]);
    }

    public function storeDomain(Request $request): JsonResponse
    {
        $name = mb_strtolower((string) $request->validate([
            'name' => ['required', 'string', 'max:191', 'regex:/^([a-z0-9-]+\.)+[a-z]{2,}$/i', 'unique:ext_subdomains_domains,name'],
        ])['name']);

        try {
            Domain::query()->create(['name' => $name, 'zone_id' => $this->cloudflare->zoneId($name)]);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->index();
    }

    public function updateDomain(Request $request, int $id): JsonResponse
    {
        Domain::query()->findOrFail($id)->update($request->validate(['enabled' => ['required', 'boolean']]));

        return $this->index();
    }

    public function destroyDomain(int $id): JsonResponse
    {
        $domain = Domain::query()->findOrFail($id);

        try {
            Record::query()->with('domain')->where('domain_id', $domain->id)->each(fn (Record $record) => $this->records->delete($record));
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        $domain->delete();

        return $this->index();
    }

    public function updateNode(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'ipv4' => ['nullable', 'ipv4'],
            'ipv6' => ['nullable', 'ipv6'],
        ]);

        NodeTarget::query()->updateOrCreate(['node_id' => Node::query()->findOrFail($id)->id], $data);

        return $this->index();
    }

    public function server(int $id): JsonResponse
    {
        $server = Server::query()->findOrFail($id);

        return new JsonResponse([
            'limit' => $this->records->limit($server),
            'custom_limit' => Limit::query()->find($server->id)?->limit,
            'records' => $this->records->records($server)->map(static fn (Record $record): array => Present::record($record))->values(),
        ]);
    }

    public function updateLimit(Request $request, int $id): JsonResponse
    {
        $server = Server::query()->findOrFail($id);
        $limit = $request->validate(['limit' => ['nullable', 'integer', 'min:0', 'max:100']])['limit'];

        $limit === null
            ? Limit::query()->whereKey($server->id)->delete()
            : Limit::query()->updateOrCreate(['server_id' => $server->id], ['limit' => $limit]);

        return $this->server($id);
    }

    public function sync(int $id): JsonResponse
    {
        $server = Server::query()->findOrFail($id);

        foreach ($this->records->records($server) as $record) {
            try {
                $this->records->sync($record, $server, force: true);
            } catch (Throwable) {
            }
        }

        return $this->server($id);
    }
}
