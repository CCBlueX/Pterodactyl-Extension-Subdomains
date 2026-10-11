<?php

declare(strict_types=1);

namespace Subdomains\Tests\Unit\Dns;

use RuntimeException;
use Subdomains\Dns\Response;
use Subdomains\Dns\Transport;

/** Records requests and answers them with queued responses. */
final class FakeTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, json: array<array-key, mixed>|null}> */
    public array $requests = [];

    /** @param list<Response> $responses */
    public function __construct(private array $responses = []) {}

    public function send(string $method, string $url, array $headers = [], ?array $json = null): Response
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'json' => $json];

        return array_shift($this->responses) ?? throw new RuntimeException("Unexpected request: {$method} {$url}");
    }
}
