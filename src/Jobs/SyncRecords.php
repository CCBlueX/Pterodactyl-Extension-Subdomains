<?php

declare(strict_types=1);

namespace Subdomains\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Subdomains\Models\Record;
use Subdomains\Records\Manager;
use Throwable;

/**
 * Brings every subdomain in line after a template or node change. Runs for up to 40 seconds, then queues itself for the rest.
 */
final class SyncRecords implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    private const int SECONDS = 40;

    public int $tries = 1;

    public int $timeout = 55;

    public function __construct(public int $after = 0)
    {
        $this->onQueue('low');
    }

    public function handle(Manager $records): void
    {
        $started = microtime(true);

        $query = Record::query()->with(['domain', 'server.allocation', 'server.allocations', 'server.node', 'server.egg'])->where('id', '>', $this->after)->orderBy('id');

        foreach ($query->lazyById(50) as $record) {
            if (microtime(true) - $started > self::SECONDS) {
                self::dispatch($record->id - 1);

                return;
            }

            try {
                $records->reconcile($record);
            } catch (Throwable) {
                // The error is stored on the record and shown to its owner.
            }
        }
    }
}
