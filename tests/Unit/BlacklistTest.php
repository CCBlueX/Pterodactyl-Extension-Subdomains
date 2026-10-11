<?php

declare(strict_types=1);

namespace Subdomains\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Subdomains\Support\Blacklist;
use Subdomains\Support\Url;

final class BlacklistTest extends TestCase
{
    public function test_blocks_exact_names_and_patterns_ignoring_case(): void
    {
        $this->assertSame('"www" is reserved.', Blacklist::reason('www', ['WWW', 'mail'], []));
        $this->assertSame('"admin-panel" is reserved.', Blacklist::reason('admin-panel', [], ['^ADMIN']));
        $this->assertSame('"shop1" is reserved.', Blacklist::reason('shop1', [], ['/^shop\d+$/']));
        $this->assertNull(Blacklist::reason('survival', ['www'], ['^admin', 'mail$']));
    }

    public function test_validates_patterns(): void
    {
        $this->assertNull(Blacklist::problem('^staff-\d+$'));
        $this->assertNull(Blacklist::problem('a~b'));
        $this->assertSame('"(unclosed" is not a valid regular expression.', Blacklist::problem('(unclosed'));
        $this->assertSame('Enter a pattern.', Blacklist::problem('  '));
        $this->assertNull(Blacklist::reason('survival', [], ['(unclosed']));
    }

    public function test_refuses_private_and_plain_http_api_urls(): void
    {
        $this->assertNotNull(Url::problem('http://example.com', false));
        $this->assertNotNull(Url::problem('https://127.0.0.1:8081', false));
        $this->assertNotNull(Url::problem('https://[::1]:8081', false));
        $this->assertNull(Url::problem('https://1.1.1.1:8081', false));
        $this->assertNull(Url::problem('http://10.0.0.5:8081', true));
        $this->assertNotNull(Url::problem('ftp://10.0.0.5/', true));
    }
}
