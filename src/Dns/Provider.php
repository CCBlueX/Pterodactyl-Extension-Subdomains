<?php

declare(strict_types=1);

namespace Subdomains\Dns;

/**
 * A DNS hosting service. Every method throws a DnsException when the service refuses the request.
 */
interface Provider
{
    /** The zone that holds the domain. Also used to test the credentials. */
    public function findZone(string $domain): Zone;

    /** @return list<string> The types of the records that already exist at exactly this name, e.g. ["A", "TXT"]. */
    public function typesAt(Zone $zone, string $name): array;

    /** Creates the record and returns it with the provider's id, when the provider has ids. */
    public function create(Zone $zone, DnsRecord $record): DnsRecord;

    /** Deletes the record. Records that are already gone are fine. */
    public function delete(Zone $zone, DnsRecord $record): void;
}
