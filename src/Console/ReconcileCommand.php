<?php

declare(strict_types=1);

namespace Subdomains\Console;

use Illuminate\Console\Command;
use Subdomains\Models\Record;
use Subdomains\Records\Manager;
use Throwable;

final class ReconcileCommand extends Command
{
    protected $signature = 'subdomains:reconcile';

    protected $description = 'Remove DNS records of deleted servers and update records whose server address changed.';

    public function handle(Manager $records): int
    {
        Record::query()->with(['domain', 'server.allocation', 'server.node', 'server.egg'])->each(function (Record $record) use ($records): void {
            try {
                $record->server === null ? $records->delete($record) : $records->sync($record, $record->server);
            } catch (Throwable $exception) {
                $this->warn("{$record->name}: {$exception->getMessage()}");
            }
        });

        return self::SUCCESS;
    }
}
