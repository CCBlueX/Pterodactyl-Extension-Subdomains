<?php

declare(strict_types=1);

namespace Subdomains\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Subdomains\Dns\DnsRecord;
use Subdomains\Support\Target;
use Subdomains\Support\Template;

final class TemplateTest extends TestCase
{
    public function test_minecraft_template_keeps_the_srv_record_for_other_ports(): void
    {
        $records = Template::minecraft()->records(new Target('203.0.113.7', null, 'node.example.net', 25504), 'play', 'play.example.com', 'Survival');

        $this->assertSame(['A', 'SRV'], array_map(static fn (DnsRecord $record): string => $record->type, $records));
        $this->assertEquals(new DnsRecord('A', 'play.example.com', '203.0.113.7'), $records[0]);
        $this->assertEquals(new DnsRecord('SRV', '_minecraft._tcp.play.example.com', 'play.example.com', priority: 0, weight: 5, port: 25504), $records[1]);
    }

    public function test_default_port_and_the_standard_template_only_get_address_records(): void
    {
        $types = static fn (array $records): array => array_map(static fn (DnsRecord $record): string => $record->type, $records);

        $this->assertSame(['A'], $types(Template::minecraft()->records(new Target('203.0.113.7', null, null, 25565), 'play', 'play.example.com', 'Survival')));
        $this->assertSame(['A', 'AAAA'], $types(Template::standard()->records(new Target('203.0.113.7', '2001:db8::1', null, 28015), 'rust', 'rust.example.com', 'Rust')));
        $this->assertSame(['AAAA'], $types((new Template(address: 'aaaa'))->records(new Target('203.0.113.7', '2001:db8::1', null, 28015), 'rust', 'rust.example.com', 'Rust')));
    }

    public function test_cname_targets_and_proxied_records(): void
    {
        $template = new Template(address: 'cname', proxied: true, comment: '{server_name} on {ip}:{port}');
        $records = $template->records(new Target('203.0.113.7', null, 'node.example.net', 8080), 'web', 'web.example.com', 'Website');

        $this->assertEquals([new DnsRecord('CNAME', 'web.example.com', 'node.example.net', proxied: true, comment: 'Website on 203.0.113.7:8080')], $records);
    }

    public function test_nodes_that_prefer_a_hostname_always_get_a_cname(): void
    {
        $records = Template::minecraft()->records(new Target(null, null, 'home.example.net', 25570, true), 'play', 'play.example.com', 'Survival');

        $this->assertSame('CNAME', $records[0]->type);
        $this->assertSame('home.example.net', $records[0]->content);
        $this->assertSame('SRV', $records[1]->type);
    }

    public function test_renders_placeholders_in_the_srv_record(): void
    {
        $template = new Template(srv: true, service: '_ts3', protocol: 'udp', priority: 10, weight: 20, srvPort: '{port}', srvTarget: '{name}.voice.example.net', ttl: 120);
        $srv = $template->records(new Target('203.0.113.7', null, null, 9987), 'team', 'team.example.com', 'Voice')[1];

        $this->assertEquals(new DnsRecord('SRV', '_ts3._udp.team.example.com', 'team.voice.example.net', 120, false, 10, 20, 9987), $srv);
        $this->assertSame('Survival at 203.0.113.7', Template::render('{server_name} at {ip}', ['server_name' => 'Survival', 'ip' => '203.0.113.7']));
        $this->assertSame('{unknown}', Template::render('{unknown}', []));
    }

    public function test_refuses_servers_without_an_address(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The server has no public address.');

        Template::standard()->records(new Target(null, null, 'node.example.net', 25565), 'play', 'play.example.com', 'Survival');
    }

    public function test_validates_templates(): void
    {
        $this->assertNull(Template::minecraft()->problem());
        $this->assertNull(Template::fromArray(Template::minecraft()->toArray())->problem());
        $this->assertSame('_minecraft', Template::fromArray(['service' => 'minecraft'])->service);
        $this->assertStringContainsString('{host}', (string) (new Template(srvTarget: '{host}'))->problem());
        $this->assertStringContainsString('Proxied', (string) (new Template(srv: true, proxied: true))->problem());
        $this->assertStringContainsString('TTL', (string) (new Template(ttl: 30))->problem());
        $this->assertStringContainsString('SRV port', (string) (new Template(srvPort: 'abc'))->problem());
    }
}
