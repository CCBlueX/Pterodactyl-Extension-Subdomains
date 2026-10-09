<?php

declare(strict_types=1);

namespace Subdomains\Http\Controllers;

use Subdomains\Models\Record;

final class Present
{
    /** @return array<string, mixed> */
    public static function record(Record $record): array
    {
        return [
            'id' => $record->id,
            'hostname' => $record->hostname(),
            'domain' => $record->domain->name,
            'target' => $record->target,
            'port' => $record->port,
            'srv' => isset($record->cloudflare_ids['srv']),
            'error' => $record->error,
            'created_at' => $record->created_at?->toAtomString(),
        ];
    }
}
