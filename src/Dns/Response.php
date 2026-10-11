<?php

declare(strict_types=1);

namespace Subdomains\Dns;

final readonly class Response
{
    /** @param array<array-key, mixed>|null $body The decoded JSON body. */
    public function __construct(public int $status, public ?array $body = null) {}

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
