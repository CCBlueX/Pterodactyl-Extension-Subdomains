<?php

declare(strict_types=1);

namespace Subdomains\Tests\Unit\Dns;

use PHPUnit\Framework\TestCase;
use Subdomains\Dns\DnsRecord;
use Subdomains\Dns\Zone;

final class DnsRecordTest extends TestCase
{
    public function test_diff_only_touches_records_that_changed(): void
    {
        $a = new DnsRecord('A', 'play.example.com', '203.0.113.7', id: 'a1');
        $srv = new DnsRecord('SRV', '_minecraft._tcp.play.example.com', 'play.example.com', priority: 0, weight: 5, port: 25570, id: 's1');
        $moved = new DnsRecord('SRV', '_minecraft._tcp.play.example.com', 'play.example.com', priority: 0, weight: 5, port: 25571);

        [$delete, $create] = DnsRecord::diff([$a, $srv], [$a->withId(null), $moved]);

        $this->assertSame([$srv], $delete);
        $this->assertSame([$moved], $create);
        $this->assertSame([[], []], DnsRecord::diff([$a], [new DnsRecord('A', 'PLAY.example.com', '203.0.113.7')]));
    }

    public function test_round_trips_through_arrays(): void
    {
        $record = new DnsRecord('CNAME', 'web.example.com', 'node.example.net', 300, true, comment: 'Website', id: '7');

        $this->assertEquals($record, DnsRecord::fromArray($record->toArray()));
        $this->assertEquals(new DnsRecord('A', 'play.example.com', '203.0.113.7', id: 'cf'), DnsRecord::fromArray(['type' => 'A', 'name' => 'play.example.com', 'content' => '203.0.113.7', 'id' => 'cf']));
    }

    public function test_zone_candidates_and_relative_names(): void
    {
        $this->assertSame(['a.mc.example.com', 'mc.example.com', 'example.com'], Zone::candidates('A.mc.example.com.'));
        $this->assertSame('play', (new Zone('1', 'example.com'))->relative('play.example.com'));
        $this->assertSame('', (new Zone('1', 'example.com'))->relative('example.com.'));
    }
}
