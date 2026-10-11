<?php

declare(strict_types=1);

namespace Subdomains\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Pterodactyl\Models\Egg;
use Subdomains\Jobs\SyncRecords;
use Subdomains\Models\RecordTemplate;
use Subdomains\Records\Templates;
use Subdomains\Settings;
use Subdomains\Support\Template;

/**
 * The default and Minecraft templates, and per-egg overrides. Saving queues an update of existing subdomains.
 */
final class TemplateController
{
    public function __construct(private readonly Templates $templates, private readonly Settings $settings, private readonly AdminController $admin) {}

    public function update(Request $request, string $key): JsonResponse
    {
        RecordTemplate::query()->updateOrCreate(['key' => $key], ['egg_id' => null, 'template' => $this->validated($request)->toArray()]);
        SyncRecords::dispatch();

        return $this->admin->index();
    }

    public function reset(string $key): JsonResponse
    {
        RecordTemplate::query()->where('key', $key)->delete();
        SyncRecords::dispatch();

        return $this->admin->index();
    }

    public function egg(int $id): JsonResponse
    {
        $egg = Egg::query()->with('tags')->findOrFail($id);
        $inherited = Templates::inheritedKey($egg);

        return new JsonResponse([
            'egg' => ['id' => $egg->id, 'name' => $egg->name],
            'override' => $this->templates->stored(Templates::eggKey($egg->id))?->toArray(),
            'inherited' => ['key' => $inherited, 'template' => $this->templates->shared($inherited)->toArray()],
            'available' => $this->settings->allowsEgg($egg->id),
        ]);
    }

    public function updateEgg(Request $request, int $id): JsonResponse
    {
        $egg = Egg::query()->findOrFail($id);
        RecordTemplate::query()->updateOrCreate(['key' => Templates::eggKey($egg->id)], ['egg_id' => $egg->id, 'template' => $this->validated($request)->toArray()]);
        SyncRecords::dispatch();

        return $this->egg($id);
    }

    public function resetEgg(int $id): JsonResponse
    {
        RecordTemplate::query()->where('key', Templates::eggKey(Egg::query()->findOrFail($id)->id))->delete();
        SyncRecords::dispatch();

        return $this->egg($id);
    }

    private function validated(Request $request): Template
    {
        $template = Template::fromArray($request->validate([
            'address' => ['required', Rule::in(Template::ADDRESSES)],
            'srv' => ['required', 'boolean'],
            'service' => ['nullable', 'string', 'max:63'],
            'protocol' => ['required', Rule::in(Template::PROTOCOLS)],
            'priority' => ['required', 'integer', 'between:0,65535'],
            'weight' => ['required', 'integer', 'between:0,65535'],
            'srv_port' => ['required', 'string', 'max:16'],
            'srv_target' => ['required', 'string', 'max:191'],
            'skip_port' => ['nullable', 'integer', 'between:1,65535'],
            'ttl' => ['required', 'integer', 'between:1,'.Template::MAX_TTL],
            'proxied' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:100'],
        ]));

        abort_if(($problem = $template->problem()) !== null, 422, $problem);

        return $template;
    }
}
