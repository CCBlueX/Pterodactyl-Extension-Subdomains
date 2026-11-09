<?php

declare(strict_types=1);

namespace Subdomains\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Subdomains\Support\Limits;

final class LimitsTest extends TestCase
{
    public function test_unlimited_is_never_full_and_zero_always_is(): void
    {
        $this->assertFalse(Limits::isFull(500, Limits::UNLIMITED));
        $this->assertTrue(Limits::isFull(0, 0));
        $this->assertFalse(Limits::isFull(1, 2));
        $this->assertTrue(Limits::isFull(2, 2));
    }
}
