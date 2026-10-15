<?php

declare(strict_types=1);

namespace Subdomains\Tests\Unit;

use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Subdomains\Dns\Bunny;
use Subdomains\Dns\DnsRecord;
use Subdomains\Dns\PowerDns;
use Subdomains\Dns\Zone;
use Subdomains\Support\Naming;
use Subdomains\Support\Target;
use Subdomains\Support\Template;

final class NamingTest extends TestCase
{
    private const array VALUES = ['username' => 'Steve', 'server' => 'Survival World', 'id' => '7unrl0q1'];

    public function test_slugifies_text_to_a_dns_label(): void
    {
        $this->assertSame('my-server', Naming::slugify('My Server!'));
        $this->assertSame('hello-world', Naming::slugify('  --Hello__World--  '));
        $this->assertSame('a-b-c', Naming::slugify('a...b   c'));
        $this->assertSame('uberleben-sudwest', Naming::slugify('Überleben Südwest'));
        $this->assertSame('strasse-aero-lodz', Naming::slugify('Straße Ærø Łódź'));
        $this->assertSame('fire-pit', Naming::slugify('🔥 Fire 🔥 Pit 🔥'));
        $this->assertSame('', Naming::slugify('🔥🔥'));
        $this->assertSame('', Naming::slugify('サーバー'));
        $this->assertSame('mc-1-21', Naming::slugify('MC 1.21'));
    }

    public function test_slugs_stay_within_the_length_without_a_trailing_dash(): void
    {
        $slug = Naming::slugify(str_repeat('a', 62).' b');

        $this->assertSame(str_repeat('a', 62), $slug);
        $this->assertSame(63, mb_strlen(Naming::slugify(str_repeat('x', 100))));
        $this->assertSame('abc', Naming::slugify('abc-def', 4));
    }

    public function test_builds_names_from_patterns(): void
    {
        $this->assertSame('steve-survival-world', $this->pick(new Naming('example.com', Naming::PATTERN)));
        $this->assertSame('mc-7unrl0q1', $this->pick(new Naming('example.com', Naming::PATTERN, 'mc-{id}')));
        $this->assertSame('steve7unrl0q1', $this->pick(new Naming('example.com', Naming::PATTERN, '{username}{id}')));
        $this->assertSame('steve-survival-world', $this->pick(new Naming('example.com', Naming::PATTERN, '{username}--{server}-')));
    }

    public function test_falls_back_to_the_server_id_when_nothing_is_left(): void
    {
        $naming = new Naming('example.com', Naming::PATTERN, '{server}');

        $this->assertSame('7unrl0q1', $naming->pick(['server' => '🔥🔥', 'id' => '7unrl0q1'], static fn (): bool => true));
        $this->assertSame('kaaaaaaa', $naming->pick(['server' => '', 'id' => ''], static fn (): bool => true, self::sequence([10, 0, 0, 0, 0, 0, 0, 0])));
    }

    public function test_fills_random_characters_into_patterns(): void
    {
        $naming = new Naming('example.com', Naming::PATTERN, '{username}-{random}', 6);

        $this->assertMatchesRegularExpression('/^steve-[a-z][a-z0-9]{5}$/', $naming->pick(self::VALUES, static fn (): bool => true));
        $this->assertTrue($naming->isRandom());
        $this->assertFalse((new Naming('example.com', Naming::PATTERN))->isRandom());
    }

    public function test_appends_numbers_to_taken_names(): void
    {
        $taken = ['steve-survival-world', 'steve-survival-world-2'];
        $naming = new Naming('example.com', Naming::PATTERN);

        $this->assertSame('steve-survival-world-3', $naming->pick(self::VALUES, static fn (string $name): bool => ! in_array($name, $taken, true)));
    }

    public function test_numbered_names_stay_within_63_characters(): void
    {
        $naming = new Naming('example.com', Naming::PATTERN, '{server}');
        $server = str_repeat('a', 62).' bcd';

        $first = $naming->pick(['server' => $server], static fn (): bool => true);
        $second = $naming->pick(['server' => $server], static fn (string $name): bool => $name !== $first);

        $this->assertSame(str_repeat('a', 62), $first);
        $this->assertSame(str_repeat('a', 61).'-2', $second);
        $this->assertSame(str_repeat('a', 60).'-10', Naming::withSuffix(str_repeat('a', 63), 10));
        $this->assertSame('ab-2', Naming::withSuffix('ab-cdef', 2, 5));
    }

    public function test_gives_up_when_every_name_is_taken(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No free name like steve-survival-world is left on example.com. Ask an administrator.');

        (new Naming('example.com', Naming::PATTERN))->pick(self::VALUES, static fn (): bool => false);
    }

    public function test_random_names_are_rolled_again_when_taken(): void
    {
        $naming = new Naming('example.com', Naming::RANDOM, length: 8);
        $seen = [];

        $name = $naming->pick([], static function (string $name) use (&$seen): bool {
            $seen[] = $name;

            return count($seen) === 3;
        });

        $this->assertCount(3, $seen);
        $this->assertSame($seen[2], $name);
        $this->assertMatchesRegularExpression('/^[a-z][a-z0-9]{7}$/', $name);
        $this->assertSame('kaaa', Naming::random(4, self::sequence([10, 0, 0, 0])));
    }

    public function test_random_lengths_are_kept_between_4_and_16(): void
    {
        $this->assertSame(4, (new Naming('example.com', Naming::RANDOM, length: 2))->length);
        $this->assertSame(16, (new Naming('example.com', Naming::RANDOM, length: 40))->length);
        $this->assertSame(16, mb_strlen((new Naming('example.com', Naming::RANDOM, length: 16))->pick([], static fn (): bool => true)));
    }

    public function test_unknown_modes_fall_back_to_custom(): void
    {
        $naming = new Naming('example.com', 'nonsense', '');

        $this->assertSame(Naming::CUSTOM, $naming->mode);
        $this->assertSame(Naming::DEFAULT_PATTERN, $naming->pattern);
        $this->assertFalse($naming->isAutomatic());
        $this->expectException(RuntimeException::class);
        $naming->pick(self::VALUES, static fn (): bool => true);
    }

    public function test_validates_patterns(): void
    {
        $this->assertNull(Naming::patternProblem('{username}-{server}'));
        $this->assertNull(Naming::patternProblem('mc-{id}-{random}'));
        $this->assertNull(Naming::patternProblem('lobby'));
        $this->assertSame('Enter a pattern, for example {username}-{server}.', Naming::patternProblem(' '));
        $this->assertSame('Unknown placeholder {name}. Use {username}, {server}, {id} or {random}.', Naming::patternProblem('{name}'));
        $this->assertSame('Besides placeholders, use lowercase letters, numbers and dashes only.', Naming::patternProblem('{username}.{server}'));
        $this->assertSame('Besides placeholders, use lowercase letters, numbers and dashes only.', Naming::patternProblem('Play-{id}'));
        $this->assertNotNull(Naming::patternProblem(str_repeat('a', 101)));
    }

    public function test_checks_names_users_type(): void
    {
        $naming = new Naming('example.com');

        $this->assertNull($naming->problem('play'));
        $this->assertNull($naming->problem('play-2'));
        $this->assertSame('Use lowercase letters, numbers and dashes only.', $naming->problem('-play'));
        $this->assertSame('Use lowercase letters, numbers and dashes only.', $naming->problem('play.mc'));
        $this->assertSame('Use lowercase letters, numbers and dashes only.', $naming->problem(''));
    }

    public function test_builds_names_below_a_range(): void
    {
        $naming = new Naming('serv.axo.im', Naming::PATTERN);
        $name = $this->pick($naming);
        $hostname = $naming->hostname($name);
        $zone = new Zone('zone1', 'axo.im');

        $this->assertSame('steve-survival-world.serv.axo.im', $hostname);
        $this->assertSame(['serv.axo.im', 'axo.im'], Zone::candidates('serv.axo.im'));
        $this->assertSame('steve-survival-world.serv', $zone->relative($hostname));

        $records = Template::minecraft()->records(new Target('203.0.113.7', null, null, 25570), $name, $hostname, 'Survival World');

        $this->assertSame(['steve-survival-world.serv.axo.im', '_minecraft._tcp.steve-survival-world.serv.axo.im'], array_map(static fn (DnsRecord $record): string => $record->name, $records));
        $this->assertSame('_minecraft._tcp.steve-survival-world.serv', Bunny::payload($zone, $records[1])['Name']);
        $this->assertSame('0 5 25570 steve-survival-world.serv.axo.im.', PowerDns::content($records[1]));
    }

    public function test_long_ranges_leave_less_room_for_the_name(): void
    {
        // 191 characters, the longest domain the extension stores.
        $range = str_repeat('r', 60).'.'.str_repeat('s', 60).'.'.str_repeat('t', 57).'.example.com';
        $naming = new Naming($range, Naming::PATTERN, '{server}');
        $name = $naming->pick(['server' => str_repeat('a', 80)], static fn (): bool => true);

        $this->assertSame(191, mb_strlen($range));
        $this->assertSame(61, $naming->maxLength());
        $this->assertSame(61, mb_strlen($name));
        $this->assertSame(253, mb_strlen($naming->hostname($name)));
        $this->assertSame("Names on {$range} can have at most 61 characters.", $naming->problem(str_repeat('a', 62)));
        $this->assertNull($naming->problem(str_repeat('a', 61)));
        $this->assertSame(63, (new Naming('serv.axo.im'))->maxLength());
    }

    /**
     * Hands out the given numbers in turn.
     *
     * @param  list<int|string>  $numbers
     * @return Closure(int): int
     */
    private static function sequence(array $numbers): Closure
    {
        return static function (int $max) use (&$numbers): int {
            return (int) array_shift($numbers) % $max;
        };
    }

    private function pick(Naming $naming): string
    {
        return $naming->pick(self::VALUES, static fn (): bool => true);
    }
}
