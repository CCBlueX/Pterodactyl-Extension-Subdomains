<?php

declare(strict_types=1);

namespace Subdomains\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Subdomains\Dns\Providers;
use Subdomains\Jobs\SyncRecords;
use Subdomains\Models\Domain;
use Subdomains\Models\Limit;
use Subdomains\Models\NodeTarget;
use Subdomains\Models\Record;
use Subdomains\Models\RecordTemplate;
use Subdomains\Records\Manager;
use Subdomains\Records\Templates;
use Subdomains\Settings;
use Subdomains\Support\Target;
use Throwable;

final class AdminController
{
    public function __construct(private readonly Manager $records, private readonly Templates $templates, private readonly Settings $settings) {}

    public function index(): JsonResponse
    {
        $targets = NodeTarget::query()->get()->keyBy('node_id');
        $counts = Record::query()->selectRaw('domain_id, count(*) as total')->groupBy('domain_id')->pluck('total', 'domain_id');
        $overrides = RecordTemplate::query()->whereNotNull('egg_id')->pluck('egg_id')->all();

        return new JsonResponse([
            'domains' => Domain::query()->orderBy('name')->get()->map(static fn (Domain $domain): array => Present::domain($domain, (int) ($counts[$domain->id] ?? 0)))->values(),
            'nodes' => Node::query()->orderBy('name')->get(['id', 'name', 'fqdn'])->map(static fn (Node $node): array => [
                'id' => $node->id,
                'name' => $node->name,
                'fqdn' => $node->fqdn,
                'mode' => $targets->get($node->id)->mode ?? 'ip',
                'ipv4' => $targets->get($node->id)?->ipv4,
                'ipv6' => $targets->get($node->id)?->ipv6,
                'hostname' => $targets->get($node->id)?->hostname,
            ])->values(),
            'eggs' => Egg::query()->with('tags')->orderBy('name')->get()->map(static fn (Egg $egg): array => [
                'id' => $egg->id,
                'name' => $egg->name,
                'minecraft' => Templates::isMinecraft($egg),
                'override' => in_array($egg->id, $overrides, true),
            ])->values(),
            'allowed_eggs' => $this->settings->allowedEggs(),
            'providers' => array_map(static fn (string $id, array $definition): array => ['id' => $id] + $definition, array_keys(Providers::DEFINITIONS), Providers::DEFINITIONS),
            'templates' => [
                Templates::DEFAULT => $this->sharedTemplate(Templates::DEFAULT),
                Templates::MINECRAFT => $this->sharedTemplate(Templates::MINECRAFT),
            ],
        ]);
    }

    public function updateNode(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(Target::MODES)],
            'ipv4' => ['nullable', 'ipv4'],
            'ipv6' => ['nullable', 'ipv6'],
            'hostname' => ['nullable', 'string', 'max:191', 'regex:/^([a-z0-9_]([a-z0-9_-]{0,61}[a-z0-9])?\.)+[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.?$/i'],
        ]);

        NodeTarget::query()->updateOrCreate(['node_id' => Node::query()->findOrFail($id)->id], $data);
        SyncRecords::dispatch();

        return $this->index();
    }

    public function updateEggs(Request $request): JsonResponse
    {
        $data = $request->validate(['eggs' => ['present', 'array'], 'eggs.*' => ['integer', 'exists:eggs,id']]);
        $this->settings->setAllowedEggs(array_map('intval', $data['eggs']));

        return $this->index();
    }

    public function server(int $id): JsonResponse
    {
        $server = Server::query()->findOrFail($id);

        return new JsonResponse([
            'limit' => $this->records->limit($server),
            'default_limit' => $this->settings->defaultLimit(),
            'custom_limit' => Limit::query()->find($server->id)?->limit,
            'records' => $this->records->records($server)->map(static fn (Record $record): array => Present::record($record, $server))->values(),
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
                // Shown as the record's error.
            }
        }

        return $this->server($id);
    }

    /** @return array{template: array<string, mixed>, custom: bool} */
    private function sharedTemplate(string $key): array
    {
        return ['template' => $this->templates->shared($key)->toArray(), 'custom' => $this->templates->stored($key) !== null];
    }
}
