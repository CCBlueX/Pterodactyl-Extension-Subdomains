<?php

declare(strict_types=1);

namespace Subdomains\Tests\Unit\Dns;

use PHPUnit\Framework\TestCase;
use Subdomains\Dns\DnsException;
use Subdomains\Dns\DnsRecord;
use Subdomains\Dns\PowerDns;
use Subdomains\Dns\Response;
use Subdomains\Dns\Zone;

final class PowerDnsTest extends TestCase
{
    private const string BASE = 'https://ns1.example.net:8081/api/v1/servers/localhost';

    public function test_finds_the_zone_and_accepts_urls_with_the_api_path(): void
    {
        $http = new FakeTransport([new Response(200, []), new Response(200, [['id' => 'example.com.', 'name' => 'example.com.']])]);

        $zone = (new PowerDns($http, 'https://ns1.example.net:8081/api/v1/', 'key'))->findZone('mc.example.com');

        $this->assertSame(['example.com.', 'example.com'], [$zone->id, $zone->name]);
        $this->assertSame(self::BASE.'/zones?zone=mc.example.com.', $http->requests[0]['url']);
        $this->assertSame(['X-API-Key' => 'key'], $http->requests[1]['headers']);
    }

    public function test_merges_new_records_into_the_existing_rrset(): void
    {
        $http = new FakeTransport([
            new Response(200, ['rrsets' => [
                ['name' => 'other.example.com.', 'type' => 'A', 'ttl' => 60, 'records' => [['content' => '198.51.100.1', 'disabled' => false]]],
                ['name' => 'play.example.com.', 'type' => 'A', 'ttl' => 60, 'records' => [['content' => '198.51.100.2', 'disabled' => false]]],
            ]]),
            new Response(204),
        ]);

        (new PowerDns($http, 'https://ns1.example.net:8081', 'key'))->create(new Zone('example.com.', 'example.com'), new DnsRecord('A', 'play.example.com', '203.0.113.7'));

        $this->assertSame(self::BASE.'/zones/example.com.?rrsets=true&rrset_name=play.example.com.&rrset_type=A', $http->requests[0]['url']);
        $this->assertSame('PATCH', $http->requests[1]['method']);
        $this->assertSame(['rrsets' => [[
            'name' => 'play.example.com.',
            'type' => 'A',
            'ttl' => 300,
            'changetype' => 'REPLACE',
            'records' => [['content' => '198.51.100.2', 'disabled' => false], ['content' => '203.0.113.7', 'disabled' => false]],
        ]]], $http->requests[1]['json']);
    }

    public function test_writes_srv_records_in_zone_file_format(): void
    {
        $http = new FakeTransport([new Response(200, ['rrsets' => []]), new Response(204)]);

        (new PowerDns($http, 'https://ns1.example.net:8081', 'key'))->create(
            new Zone('example.com.', 'example.com'),
            new DnsRecord('SRV', '_minecraft._tcp.play.example.com', 'play.example.com', 120, priority: 0, weight: 5, port: 25570),
        );

        $this->assertSame(['content' => '0 5 25570 play.example.com.', 'disabled' => false], $http->requests[1]['json']['rrsets'][0]['records'][0]);
        $this->assertSame(120, $http->requests[1]['json']['rrsets'][0]['ttl']);
    }

    public function test_deleting_keeps_other_records_of_the_rrset(): void
    {
        $rrset = ['name' => 'play.example.com.', 'type' => 'A', 'ttl' => 60, 'records' => [['content' => '198.51.100.2'], ['content' => '203.0.113.7']]];
        $http = new FakeTransport([new Response(200, ['rrsets' => [$rrset]]), new Response(204)]);

        (new PowerDns($http, 'https://ns1.example.net:8081', 'key'))->delete(new Zone('example.com.', 'example.com'), new DnsRecord('A', 'play.example.com', '203.0.113.7'));

        $this->assertSame(['rrsets' => [[
            'name' => 'play.example.com.',
            'type' => 'A',
            'ttl' => 60,
            'changetype' => 'REPLACE',
            'records' => [['content' => '198.51.100.2']],
        ]]], $http->requests[1]['json']);
    }

    public function test_deleting_the_last_record_deletes_the_rrset_and_missing_records_are_skipped(): void
    {
        $http = new FakeTransport([
            new Response(200, ['rrsets' => [['name' => 'play.example.com.', 'type' => 'CNAME', 'ttl' => 300, 'records' => [['content' => 'node.example.net.']]]]]),
            new Response(204),
            new Response(200, ['rrsets' => []]),
        ]);
        $provider = new PowerDns($http, 'https://ns1.example.net:8081', 'key');
        $zone = new Zone('example.com.', 'example.com');

        $provider->delete($zone, new DnsRecord('CNAME', 'play.example.com', 'node.example.net'));
        $provider->delete($zone, new DnsRecord('CNAME', 'play.example.com', 'node.example.net'));

        $this->assertSame(['rrsets' => [['name' => 'play.example.com.', 'type' => 'CNAME', 'changetype' => 'DELETE']]], $http->requests[1]['json']);
        $this->assertCount(3, $http->requests);
    }

    public function test_reports_powerdns_errors(): void
    {
        $this->expectException(DnsException::class);
        $this->expectExceptionMessage('PowerDNS: RRset play.example.com. IN CNAME: Conflicts with pre-existing RRset (HTTP 422)');

        $http = new FakeTransport([new Response(200, ['rrsets' => []]), new Response(422, ['error' => 'RRset play.example.com. IN CNAME: Conflicts with pre-existing RRset'])]);
        (new PowerDns($http, 'https://ns1.example.net:8081', 'key'))->create(new Zone('example.com.', 'example.com'), new DnsRecord('CNAME', 'play.example.com', 'node.example.net'));
    }
}
