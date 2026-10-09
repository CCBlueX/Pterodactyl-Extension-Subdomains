<?php

declare(strict_types=1);

namespace Subdomains\Cloudflare;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Services\Extensions\ExtensionSettingsRegistry;
use RuntimeException;

final readonly class Client
{
    private const string API = 'https://api.cloudflare.com/client/v4';

    public function __construct(private ExtensionSettingsRegistry $settings) {}

    public function zoneId(string $domain): string
    {
        $zones = $this->send('get', '/zones', ['name' => $domain]);

        return (string) ($zones[0]['id'] ?? throw new RuntimeException("Cloudflare has no zone named {$domain} for this API token."));
    }

    /**
     * @param  array<string, mixed>  $record
     * @return string The record id.
     */
    public function create(string $zone, array $record): string
    {
        return (string) $this->send('post', "/zones/{$zone}/dns_records", $record)['id'];
    }

    public function delete(string $zone, string $record): void
    {
        try {
            $this->send('delete', "/zones/{$zone}/dns_records/{$record}");
        } catch (RuntimeException $exception) {
            // A record someone already removed by hand is gone either way.
            if (! str_contains($exception->getMessage(), '81044')) {
                throw $exception;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function send(string $method, string $path, array $data = []): array
    {
        try {
            /** @var Response $response */
            $response = $this->request()->{$method}(self::API.$path, $data);
        } catch (ConnectionException) {
            throw new RuntimeException('Cloudflare could not be reached.');
        }

        $body = $response->json();

        if (! $response->successful() || ($body['success'] ?? false) !== true) {
            $error = $body['errors'][0] ?? null;

            throw new RuntimeException('Cloudflare: '.($error ? "{$error['message']} ({$error['code']})" : "HTTP {$response->status()}"));
        }

        return (array) ($body['result'] ?? []);
    }

    private function request(): PendingRequest
    {
        $token = (string) ($this->settings->get('subdomains')?->get('cloudflare_token') ?? '');

        if ($token === '') {
            throw new RuntimeException('Add a Cloudflare API token in the extension settings first.');
        }

        return Http::withToken($token)->acceptJson()->timeout(15);
    }
}
