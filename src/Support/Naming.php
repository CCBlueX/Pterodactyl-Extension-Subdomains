<?php

declare(strict_types=1);

namespace Subdomains\Support;

use Closure;
use Normalizer;
use RuntimeException;

/**
 * How the left part of a subdomain is chosen on a domain or range: typed by the user, built from a pattern or random.
 */
final readonly class Naming
{
    public const string CUSTOM = 'custom';

    public const string PATTERN = 'pattern';

    public const string RANDOM = 'random';

    public const array MODES = [self::CUSTOM, self::PATTERN, self::RANDOM];

    public const array PLACEHOLDERS = ['username', 'server', 'id', 'random'];

    public const string DEFAULT_PATTERN = '{username}-{server}';

    public const int DEFAULT_LENGTH = 8;

    public const int MIN_LENGTH = 4;

    public const int MAX_LENGTH = 16;

    public const int MAX_PATTERN = 100;

    private const int MAX_LABEL = 63;

    private const int MAX_HOSTNAME = 253;

    private const int ATTEMPTS = 50;

    // Without look-alikes (l, o, 0, 1), and random names start with a letter.
    private const string LETTERS = 'abcdefghijkmnpqrstuvwxyz';

    private const string CHARACTERS = 'abcdefghijkmnpqrstuvwxyz23456789';

    /** Latin letters spelled in ASCII, so names come out the same with or without the intl extension. */
    private const array LATIN = [
        'a' => 'àáâãäåāăą', 'c' => 'çćĉċč', 'd' => 'ďđð', 'e' => 'èéêëēĕėęě', 'g' => 'ĝğġģ', 'h' => 'ĥħ',
        'i' => 'ìíîïĩīĭįı', 'j' => 'ĵ', 'k' => 'ķ', 'l' => 'ĺļľŀł', 'n' => 'ñńņňŉ', 'o' => 'òóôõöøōŏő',
        'r' => 'ŕŗř', 's' => 'śŝşšſ', 't' => 'ţťŧ', 'u' => 'ùúûüũūŭůűų', 'w' => 'ŵ', 'y' => 'ýÿŷ', 'z' => 'źżž',
        'ae' => 'æ', 'oe' => 'œ', 'ss' => 'ß', 'th' => 'þ', 'ij' => 'ĳ', 'ng' => 'ŋ',
    ];

    public string $mode;

    public string $pattern;

    public int $length;

    /** @param string $domain The domain or range the names go below, e.g. "serv.example.com". */
    public function __construct(public string $domain, string $mode = self::CUSTOM, ?string $pattern = null, int $length = self::DEFAULT_LENGTH)
    {
        $this->mode = in_array($mode, self::MODES, true) ? $mode : self::CUSTOM;
        $this->pattern = mb_trim((string) $pattern) ?: self::DEFAULT_PATTERN;
        $this->length = max(self::MIN_LENGTH, min(self::MAX_LENGTH, $length));
    }

    /** Lowercase a-z, 0-9 and single dashes, without a dash at either end. Other characters are dropped, accents removed. */
    public static function slugify(string $text, int $max = self::MAX_LABEL): string
    {
        $text = self::latin(mb_strtolower($text));

        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_KD) ?: $text;
        }

        $slug = mb_trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($text)), '-');

        return mb_rtrim(mb_substr($slug, 0, max(0, $max)), '-');
    }

    /**
     * Lowercase letters and digits, starting with a letter.
     *
     * @param  Closure(int): int  $random  A number from 0 to the argument minus one.
     */
    public static function random(int $length, Closure $random): string
    {
        $name = self::LETTERS[$random(mb_strlen(self::LETTERS))];

        for ($i = 1; $i < $length; $i++) {
            $name .= self::CHARACTERS[$random(mb_strlen(self::CHARACTERS))];
        }

        return $name;
    }

    /** "name" for the first, then "name-2", "name-3"... shortened so the whole label stays within $max characters. */
    public static function withSuffix(string $base, int $number, int $max = self::MAX_LABEL): string
    {
        if ($number < 2) {
            return mb_rtrim(mb_substr($base, 0, $max), '-');
        }

        $suffix = "-{$number}";

        return mb_rtrim(mb_substr($base, 0, $max - mb_strlen($suffix)), '-').$suffix;
    }

    /** Why the pattern cannot be saved, or null when it is fine. */
    public static function patternProblem(string $pattern): ?string
    {
        $pattern = mb_trim($pattern);
        preg_match_all('/\{([^}]*)\}/', $pattern, $matches);
        $unknown = array_diff($matches[1], self::PLACEHOLDERS);

        return match (true) {
            $pattern === '' => 'Enter a pattern, for example {username}-{server}.',
            mb_strlen($pattern) > self::MAX_PATTERN => 'Use a pattern of at most '.self::MAX_PATTERN.' characters.',
            $unknown !== [] => 'Unknown placeholder {'.reset($unknown).'}. Use {username}, {server}, {id} or {random}.',
            preg_match('/^[a-z0-9-]*$/', (string) preg_replace('/\{[a-z]+\}/', '', $pattern)) !== 1 => 'Besides placeholders, use lowercase letters, numbers and dashes only.',
            default => null,
        };
    }

    public function isAutomatic(): bool
    {
        return $this->mode !== self::CUSTOM;
    }

    /** Whether a new name can come out differently each time. */
    public function isRandom(): bool
    {
        return $this->mode === self::RANDOM || ($this->mode === self::PATTERN && str_contains($this->pattern, '{random}'));
    }

    public function hostname(string $name): string
    {
        return "{$name}.{$this->domain}";
    }

    /** The longest name that still fits below the domain: 63 characters, less for very long ranges. */
    public function maxLength(): int
    {
        return max(1, min(self::MAX_LABEL, self::MAX_HOSTNAME - mb_strlen($this->domain) - 1));
    }

    /** Why a name a user typed cannot be used on this domain, or null. Taken and reserved names are checked elsewhere. */
    public function problem(string $name): ?string
    {
        return match (true) {
            ! Names::isValidLabel($name) => 'Use lowercase letters, numbers and dashes only.',
            mb_strlen($name) > $this->maxLength() => "Names on {$this->domain} can have at most {$this->maxLength()} characters.",
            default => null,
        };
    }

    /**
     * A free name for a pattern or random domain. Patterns get -2, -3... when taken, random names are rolled again.
     *
     * @param  array{username?: string, server?: string, id?: string}  $values
     * @param  Closure(string): bool  $isFree
     * @param  (Closure(int): int)|null  $random  A number from 0 to the argument minus one.
     *
     * @throws RuntimeException when no free name is found
     */
    public function pick(array $values, Closure $isFree, ?Closure $random = null): string
    {
        $random ??= static fn (int $max): int => random_int(0, $max - 1);
        $max = $this->maxLength();

        if ($this->mode === self::RANDOM) {
            for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
                if ($isFree($name = self::random(min($this->length, $max), $random))) {
                    return $name;
                }
            }

            throw new RuntimeException('No free random name was found. Try again.');
        }

        if ($this->mode !== self::PATTERN) {
            throw new RuntimeException("Names on {$this->domain} are typed by the user.");
        }

        $base = $this->render($values, $random, $max);

        for ($number = 1; $number <= self::ATTEMPTS; $number++) {
            if ($isFree($name = self::withSuffix($base, $number, $max))) {
                return $name;
            }
        }

        throw new RuntimeException("No free name like {$base} is left on {$this->domain}. Ask an administrator.");
    }

    private static function latin(string $text): string
    {
        $map = [];

        foreach (self::LATIN as $ascii => $characters) {
            foreach (mb_str_split($characters) as $character) {
                $map[$character] = $ascii;
            }
        }

        return strtr($text, $map);
    }

    /**
     * The pattern filled in. When nothing usable is left, for example a server named only with emoji, the short server id is used.
     *
     * @param  array{username?: string, server?: string, id?: string}  $values
     * @param  Closure(int): int  $random
     */
    private function render(array $values, Closure $random, int $max): string
    {
        $text = (string) preg_replace_callback(
            '/\{([a-z]+)\}/',
            fn (array $match): string => $match[1] === 'random' ? self::random($this->length, $random) : (string) ($values[$match[1]] ?? ''),
            $this->pattern,
        );

        return self::slugify($text, $max) ?: self::slugify($values['id'] ?? '', $max) ?: self::random(min($this->length, $max), $random);
    }
}
