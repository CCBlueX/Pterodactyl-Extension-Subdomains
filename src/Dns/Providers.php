<?php

declare(strict_types=1);

namespace Subdomains\Dns;

use Subdomains\Models\Domain;
use Subdomains\Settings;
use Subdomains\Support\Url;

/**
 * The DNS providers a domain can use, the credential fields each one needs, and the provider instance for a domain.
 */
final readonly class Providers
{
    public const array DEFINITIONS = [
        'cloudflare' => [
            'label' => 'Cloudflare',
            'fields' => [
                ['key' => 'token', 'label' => 'API token', 'secret' => true, 'required' => false, 'placeholder' => '', 'help' => 'A token with Zone: Read and DNS: Edit for this domain.'],
            ],
        ],
        'bunny' => [
            'label' => 'Bunny DNS',
            'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'secret' => true, 'required' => true, 'placeholder' => '', 'help' => 'Your account API key, found under Account settings → API.'],
            ],
        ],
        'powerdns' => [
            'label' => 'PowerDNS',
            'fields' => [
                ['key' => 'url', 'label' => 'API URL', 'secret' => false, 'required' => true, 'placeholder' => 'https://ns1.example.com:8081', 'help' => 'The address of the PowerDNS HTTP API (webserver).'],
                ['key' => 'api_key', 'label' => 'API key', 'secret' => true, 'required' => true, 'placeholder' => '', 'help' => 'The api-key from pdns.conf.'],
                ['key' => 'server', 'label' => 'Server ID', 'secret' => false, 'required' => false, 'placeholder' => 'localhost', 'help' => 'Leave empty for localhost.'],
            ],
        ],
    ];

    public function __construct(private HttpTransport $transport, private Settings $settings) {}

    /** @return array<string, list<string>> Validation rules for a provider's credentials. */
    public static function rules(string $provider): array
    {
        $rules = [];

        foreach (self::DEFINITIONS[$provider]['fields'] ?? [] as $field) {
            $rules["credentials.{$field['key']}"] = [$field['required'] ? 'required' : 'nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    /** @return array<string, string> Field labels for validation messages. */
    public static function attributes(string $provider): array
    {
        $labels = [];

        foreach (self::DEFINITIONS[$provider]['fields'] ?? [] as $field) {
            $labels["credentials.{$field['key']}"] = $field['label'];
        }

        return $labels;
    }

    /**
     * The credentials as the browser may see them: plain values for normal fields, null for secrets.
     *
     * @param  array<string, string>  $credentials
     * @return array{values: array<string, string|null>, secrets: list<string>}
     */
    public static function visible(string $provider, array $credentials): array
    {
        $values = [];
        $secrets = [];

        foreach (self::DEFINITIONS[$provider]['fields'] ?? [] as $field) {
            $values[$field['key']] = $field['secret'] ? null : ($credentials[$field['key']] ?? null);

            if ($field['secret'] && ($credentials[$field['key']] ?? '') !== '') {
                $secrets[] = $field['key'];
            }
        }

        return ['values' => $values, 'secrets' => $secrets];
    }

    public function for(Domain $domain): Provider
    {
        return $this->make($domain->provider, $domain->credentials ?? []);
    }

    /** @param array<string, string> $credentials */
    public function make(string $provider, array $credentials): Provider
    {
        return match ($provider) {
            'cloudflare' => new Cloudflare($this->transport, ($credentials['token'] ?? '') ?: $this->settings->cloudflareToken() ?: throw new DnsException('Add a Cloudflare API token to this domain first.')),
            'bunny' => new Bunny($this->transport, ($credentials['api_key'] ?? '') ?: throw new DnsException('Add a Bunny API key to this domain first.')),
            'powerdns' => new PowerDns($this->transport, $this->powerDnsUrl($credentials['url'] ?? ''), ($credentials['api_key'] ?? '') ?: throw new DnsException('Add a PowerDNS API key to this domain first.'), ($credentials['server'] ?? '') ?: 'localhost'),
            default => throw new DnsException("Unknown DNS provider \"{$provider}\"."),
        };
    }

    private function powerDnsUrl(string $url): string
    {
        $problem = Url::problem($url, $this->settings->allowPrivateTargets());

        return $problem === null ? $url : throw new DnsException("PowerDNS API URL: {$problem}");
    }
}
