<?php

declare(strict_types=1);

namespace Subdomains\Dns;

/**
 * Sends JSON requests for the providers, so their request building can be tested without the framework.
 */
interface Transport
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<array-key, mixed>|null  $json
     *
     * @throws DnsException when the host cannot be reached
     */
    public function send(string $method, string $url, array $headers = [], ?array $json = null): Response;
}
