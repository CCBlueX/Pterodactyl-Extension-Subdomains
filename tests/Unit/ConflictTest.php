<?php

declare(strict_types=1);

namespace Subdomains\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Subdomains\Support\Conflict;

final class ConflictTest extends TestCase
{
    public function test_a_name_without_records_is_free(): void
    {
        $this->assertNull(Conflict::reason('play.example.com', []));
        $this->assertNull(Conflict::reason('play.example.com', ['']));
    }

    public function test_existing_records_block_the_name_and_are_listed_once(): void
    {
        $this->assertSame(
            'play.example.com already has DNS records (A, TXT) that this panel did not create. Pick another name, or remove them at the DNS provider first.',
            Conflict::reason('play.example.com', ['A', 'TXT', 'A']),
        );
    }
}
