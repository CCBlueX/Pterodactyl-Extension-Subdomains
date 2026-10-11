<?php

declare(strict_types=1);

namespace Subdomains\Tests\Unit\Dns;

use PHPUnit\Framework\TestCase;
use Subdomains\Dns\Bunny;
use Subdomains\Dns\DnsException;
use Subdomains\Dns\DnsRecord;
use Subdomains\Dns\Response;
use Subdomains\Dns\Zone;

final class BunnyTest extends TestCase
{
    public function test_finds_the_zone_by_its_exact_name(): void
    {
        $http = new FakeTransport([new Response(200, ['Items' => [['Id' => 12, 'Domain' => 'old-example.com'], ['Id' => 34, 'Domain' => 'example.com']]])]);

        $zone = (new Bunny($http, 'key'))->findZone('example.com');

        $this->assertSame(['34', 'example.com'], [$zone->id, $zone->name]);
        $this->assertSame('https://api.bunny.net/dnszone?search=example.com&perPage=1000', $http->requests[0]['url']);
        $this->assertSame(['AccessKey' => 'key'], $http->requests[0]['headers']);
    }

    public function test_builds_payloads_with_names_relative_to_the_zone(): void
    {
        $zone = new Zone('34', 'example.com');

        $this->assertSame(
            ['Type' => 0, 'Name' => 'play.mc', 'Value' => '203.0.113.7', 'Ttl' => 300],
            Bunny::payload($zone, new DnsRecord('A', 'play.mc.example.com', '203.0.113.7')),
        );
        $this->assertSame(
            ['Type' => 8, 'Name' => '_minecraft._tcp.play', 'Value' => 'play.example.com', 'Ttl' => 120, 'Priority' => 0, 'Weight' => 5, 'Port' => 25570, 'Comment' => 'Lobby'],
            Bunny::payload($zone, new DnsRecord('SRV', '_minecraft._tcp.play.example.com', 'play.example.com', 120, priority: 0, weight: 5, port: 25570, comment: 'Lobby')),
        );
    }

    public function test_creates_and_deletes_records(): void
    {
        $http = new FakeTransport([new Response(201, ['Id' => 99]), new Response(404)]);
        $provider = new Bunny($http, 'key');
        $zone = new Zone('34', 'example.com');

        $record = $provider->create($zone, new DnsRecord('AAAA', 'play.example.com', '2001:db8::7'));
        $provider->delete($zone, $record);

        $this->assertSame('99', $record->id);
        $this->assertSame(['PUT', 'https://api.bunny.net/dnszone/34/records'], [$http->requests[0]['method'], $http->requests[0]['url']]);
        $this->assertSame(1, $http->requests[0]['json']['Type']);
        $this->assertSame(['DELETE', 'https://api.bunny.net/dnszone/34/records/99'], [$http->requests[1]['method'], $http->requests[1]['url']]);
    }

    public function test_reports_a_refused_key(): void
    {
        $this->expectException(DnsException::class);
        $this->expectExceptionMessage('Bunny DNS: the API key was refused.');

        (new Bunny(new FakeTransport([new Response(401)]), 'wrong'))->findZone('example.com');
    }
}
