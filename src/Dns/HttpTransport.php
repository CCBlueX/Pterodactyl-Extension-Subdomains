<?php

declare(strict_types=1);

namespace Subdomains\Dns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

final readonly class HttpTransport implements Transport
{
    public function __construct(private Factory $http) {}

    public function send(string $method, string $url, array $headers = [], ?array $json = null): Response
    {
        $request = $this->http->withHeaders($headers)->acceptJson()->timeout(15)->withoutRedirecting();

        try {
            $response = $request->send($method, $url, $json === null ? [] : ['json' => $json]);
        } catch (ConnectionException) {
            throw new DnsException(parse_url($url, PHP_URL_HOST).' could not be reached.');
        }

        $body = $response->json();

        return new Response($response->status(), is_array($body) ? $body : null);
    }
}
