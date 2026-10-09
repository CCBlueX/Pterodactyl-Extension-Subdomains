<?php

declare(strict_types=1);

namespace Subdomains\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Subdomains\Support\RecordPlan;

final class RecordPlanTest extends TestCase
{
    public function test_minecraft_servers_on_other_ports_get_an_srv_record(): void
    {
        $records = RecordPlan::for('play.example.com', '203.0.113.7', null, 25504, true);

        $this->assertSame(['a', 'srv'], array_keys($records));
        $this->assertSame('_minecraft._tcp.play.example.com', $records['srv']['name']);
        $this->assertSame(['priority' => 0, 'weight' => 5, 'port' => 25504, 'target' => 'play.example.com'], $records['srv']['data']);
    }

    public function test_default_port_and_other_games_only_get_address_records(): void
    {
        $this->assertSame(['a'], array_keys(RecordPlan::for('play.example.com', '203.0.113.7', null, 25565, true)));
        $this->assertSame(['a', 'aaaa'], array_keys(RecordPlan::for('rust.example.com', '203.0.113.7', '2001:db8::1', 28015, false)));
        $this->assertSame([], RecordPlan::for('x.example.com', null, null, 25504, true));
    }

    public function test_validates_names_and_addresses(): void
    {
        $this->assertTrue(RecordPlan::isValidName('my-server1'));
        $this->assertFalse(RecordPlan::isValidName('-bad'));
        $this->assertFalse(RecordPlan::isValidName('Upper'));
        $this->assertFalse(RecordPlan::isValidName('a.b'));
        $this->assertSame('51.38.104.163', RecordPlan::publicIpv4('51.38.104.163'));
        $this->assertNull(RecordPlan::publicIpv4('0.0.0.0'));
        $this->assertNull(RecordPlan::publicIpv4('10.0.0.5'));
    }
}
