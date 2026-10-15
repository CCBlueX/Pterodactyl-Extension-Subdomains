<?php

declare(strict_types=1);

namespace Subdomains\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Subdomains\Support\Names;
use Subdomains\Support\Target;

final class TargetTest extends TestCase
{
    public function test_ip_mode_uses_the_node_address_then_the_allocation_then_the_node_hostname(): void
    {
        $never = fn (string $host): string => $this->fail("Looked up {$host}.");

        $this->assertSame('198.51.100.9', Target::for(['ipv4' => '198.51.100.9'], 'node.example.net', '203.0.113.7', null, 25565, $never)->ipv4);
        $this->assertSame('203.0.113.7', Target::for([], 'node.example.net', '203.0.113.7', null, 25565, $never)->ipv4);
        $this->assertSame('203.0.113.8', Target::for([], 'node.example.net', '0.0.0.0', '203.0.113.8', 25565, $never)->ipv4);
        $this->assertSame('203.0.113.9', Target::for([], 'node.example.net', '10.0.0.5', null, 25565, static fn (): string => '203.0.113.9')->ipv4);
        $this->assertNull(Target::for([], 'node.example.net', '10.0.0.5', null, 25565, static fn (string $host): string => $host)->ipv4);
    }

    public function test_alias_mode_follows_the_allocation_alias(): void
    {
        $lookup = static fn (): string => '';

        $this->assertSame('203.0.113.8', Target::for(['mode' => 'alias'], 'node.example.net', '0.0.0.0', '203.0.113.8', 1, $lookup)->ipv4);
        $this->assertSame('2606:4700:4700::1111', Target::for(['mode' => 'alias'], 'node.example.net', '0.0.0.0', '2606:4700:4700::1111', 1, $lookup)->ipv6);

        $hostname = Target::for(['mode' => 'alias'], 'node.example.net', '0.0.0.0', 'Home.Example.org', 1, $lookup);
        $this->assertTrue($hostname->preferHostname);
        $this->assertSame('home.example.org', $hostname->address());
    }

    public function test_hostname_mode_points_at_the_node_hostname(): void
    {
        $target = Target::for(['mode' => 'hostname', 'hostname' => 'dyn.example.net.'], 'node.example.net', '203.0.113.7', null, 25570, static fn (): string => '');

        $this->assertTrue($target->preferHostname);
        $this->assertSame('dyn.example.net', $target->hostname);
        $this->assertSame('node.example.net', Target::for(['mode' => 'hostname'], 'node.example.net', '203.0.113.7', null, 1, static fn (): string => '')->hostname);
    }

    public function test_validates_names_and_addresses(): void
    {
        $this->assertTrue(Names::isValidLabel('my-server1'));
        $this->assertFalse(Names::isValidLabel('-bad'));
        $this->assertFalse(Names::isValidLabel('Upper'));
        $this->assertFalse(Names::isValidLabel('a.b'));
        $this->assertTrue(Names::isHostname('node1.example.com'));
        $this->assertFalse(Names::isHostname('localhost'));
        $this->assertSame('51.38.104.163', Names::publicIpv4('51.38.104.163'));
        $this->assertNull(Names::publicIpv4('0.0.0.0'));
        $this->assertNull(Names::publicIpv4('10.0.0.5'));
    }
}
