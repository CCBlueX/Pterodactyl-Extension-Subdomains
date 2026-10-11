<?php

declare(strict_types=1);

namespace Subdomains\Dns;

/**
 * One DNS record as the extension wants it. SRV records keep their target in $content.
 */
final readonly class DnsRecord
{
    public const int AUTO_TTL = 1;

    public function __construct(
        public string $type,
        public string $name,
        public string $content,
        public int $ttl = self::AUTO_TTL,
        public bool $proxied = false,
        public int $priority = 0,
        public int $weight = 0,
        public int $port = 0,
        public string $comment = '',
        public ?string $id = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['type'] ?? ''),
            (string) ($data['name'] ?? ''),
            (string) ($data['content'] ?? ''),
            (int) ($data['ttl'] ?? self::AUTO_TTL),
            (bool) ($data['proxied'] ?? false),
            (int) ($data['priority'] ?? 0),
            (int) ($data['weight'] ?? 0),
            (int) ($data['port'] ?? 0),
            (string) ($data['comment'] ?? ''),
            isset($data['id']) && $data['id'] !== '' ? (string) $data['id'] : null,
        );
    }

    /**
     * The records to delete and to create so $stored becomes $desired. Records that did not change stay.
     *
     * @param  list<self>  $stored
     * @param  list<self>  $desired
     * @return array{0: list<self>, 1: list<self>}
     */
    public static function diff(array $stored, array $desired): array
    {
        $wanted = array_map(static fn (self $record): string => $record->key(), $desired);
        $existing = array_map(static fn (self $record): string => $record->key(), $stored);

        return [
            array_values(array_filter($stored, static fn (self $record): bool => ! in_array($record->key(), $wanted, true))),
            array_values(array_filter($desired, static fn (self $record): bool => ! in_array($record->key(), $existing, true))),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'content' => $this->content,
            'ttl' => $this->ttl,
            'proxied' => $this->proxied,
            'priority' => $this->priority,
            'weight' => $this->weight,
            'port' => $this->port,
            'comment' => $this->comment,
            'id' => $this->id,
        ];
    }

    public function withId(?string $id): self
    {
        return new self($this->type, $this->name, $this->content, $this->ttl, $this->proxied, $this->priority, $this->weight, $this->port, $this->comment, $id);
    }

    /** Everything that makes up the record in DNS, without the provider's id. */
    public function key(): string
    {
        return implode('|', [
            $this->type,
            mb_strtolower($this->name),
            mb_strtolower($this->content),
            $this->ttl,
            $this->proxied ? 1 : 0,
            $this->priority,
            $this->weight,
            $this->port,
            $this->comment,
        ]);
    }
}
