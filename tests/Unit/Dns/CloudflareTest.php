<?php

declare(strict_types=1);

namespace Subdomains\Tests\Unit\Dns;

use PHPUnit\Framework\TestCase;
use Subdomains\Dns\Cloudflare;
use Subdomains\Dns\DnsException;
use Subdomains\Dns\DnsRecord;
use Subdomains\Dns\Response;
use Subdomains\Dns\Zone;

final class CloudflareTest extends TestCase
{
    public function test_finds_the_zone_of_a_subdomain_domain(): void
    {
        $http = new FakeTransport([
            new Response(200, ['success' => true, 'result' => []]),
            new Response(200, ['success' => true, 'result' => [['id' => 'zone1', 'name' => 'example.com']]]),
        ]);

        $zone = (new Cloudflare($http, 'secret'))->findZone('mc.example.com');

        $this->assertSame(['zone1', 'example.com'], [$zone->id, $zone->name]);
        $this->assertSame('https://api.cloudflare.com/client/v4/zones?name=mc.example.com', $http->requests[0]['url']);
        $this->assertSame('https://api.cloudflare.com/client/v4/zones?name=example.com', $http->requests[1]['url']);
        $this->assertSame(['Authorization' => 'Bearer secret'], $http->requests[0]['headers']);
    }

    public function test_builds_the_same_payloads_as_before(): void
    {
        $this->assertSame(
            ['type' => 'A', 'name' => 'play.example.com', 'ttl' => 1, 'content' => '203.0.113.7', 'proxied' => false],
            Cloudflare::payload(new DnsRecord('A', 'play.example.com', '203.0.113.7')),
        );
        $this->assertSame(
            ['type' => 'SRV', 'name' => '_minecraft._tcp.play.example.com', 'ttl' => 1, 'data' => ['priority' => 0, 'weight' => 5, 'port' => 25570, 'target' => 'play.example.com']],
            Cloudflare::payload(new DnsRecord('SRV', '_minecraft._tcp.play.example.com', 'play.example.com', priority: 0, weight: 5, port: 25570)),
        );
        $this->assertSame(
            ['type' => 'CNAME', 'name' => 'web.example.com', 'ttl' => 1, 'content' => 'node.example.net', 'proxied' => true, 'comment' => 'Lobby'],
            Cloudflare::payload(new DnsRecord('CNAME', 'web.example.com', 'node.example.net', 300, true, comment: 'Lobby')),
        );
    }

    public function test_creates_and_deletes_records_by_id(): void
    {
        $http = new FakeTransport([
            new Response(200, ['success' => true, 'result' => ['id' => 'rec1']]),
            new Response(404, ['success' => false, 'errors' => [['code' => 81044, 'message' => 'Record does not exist.']]]),
        ]);
        $provider = new Cloudflare($http, 'secret');
        $zone = new Zone('zone1', 'example.com');

        $record = $provider->create($zone, new DnsRecord('A', 'play.example.com', '203.0.113.7'));
        $provider->delete($zone, $record);

        $this->assertSame('rec1', $record->id);
        $this->assertSame(['POST', 'https://api.cloudflare.com/client/v4/zones/zone1/dns_records'], [$http->requests[0]['method'], $http->requests[0]['url']]);
        $this->assertSame(['DELETE', 'https://api.cloudflare.com/client/v4/zones/zone1/dns_records/rec1'], [$http->requests[1]['method'], $http->requests[1]['url']]);
    }

    public function test_reports_cloudflare_errors(): void
    {
        $http = new FakeTransport([new Response(403, ['success' => false, 'errors' => [['code' => 10000, 'message' => 'Authentication error']]])]);

        $this->expectException(DnsException::class);
        $this->expectExceptionMessage('Cloudflare: Authentication error (10000)');

        (new Cloudflare($http, 'wrong'))->create(new Zone('zone1', 'example.com'), new DnsRecord('A', 'play.example.com', '203.0.113.7'));
    }
}
