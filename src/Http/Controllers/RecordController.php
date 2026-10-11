<?php

declare(strict_types=1);

namespace Subdomains\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Subdomains\Models\Record;
use Subdomains\Records\Manager;

/**
 * Every subdomain on the panel, for administrators.
 */
final class RecordController
{
    private const int PER_PAGE = 25;

    public function __construct(private readonly Manager $records) {}

    public function index(Request $request): JsonResponse
    {
        $search = mb_trim((string) $request->query('search', ''));
        $page = Record::query()
            ->with(['domain', 'server.allocations'])
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

                $query->where(static function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('note', 'like', $like)
                        ->orWhere('server_uuid', 'like', $like)
                        ->orWhereHas('domain', static fn (Builder $domain) => $domain->whereRaw("concat(ext_subdomains_records.name, '.', ext_subdomains_domains.name) like ?", [$like]))
                        ->orWhereHas('server', static fn (Builder $server) => $server->where('name', 'like', $like));
                });
            })
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return new JsonResponse([
            'data' => collect($page->items())->map(static fn (Record $record): array => Present::record($record, $record->server) + [
                'server' => $record->server === null ? null : ['id' => $record->server->id, 'name' => $record->server->name, 'identifier' => $record->server->uuidShort],
                'server_uuid' => $record->server_uuid,
            ])->values(),
            'meta' => ['pagination' => [
                'total' => $page->total(),
                'count' => count($page->items()),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'total_pages' => $page->lastPage(),
            ]],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->records->delete(Record::query()->with('domain')->findOrFail($id));
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return new JsonResponse(['deleted' => true]);
    }
}
