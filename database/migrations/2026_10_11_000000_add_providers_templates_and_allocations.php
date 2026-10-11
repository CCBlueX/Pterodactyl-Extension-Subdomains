<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pterodactyl\Services\Extensions\ExtensionRepository;

/**
 * 1.1: DNS providers with credentials per domain, record templates, allocation choice, notes and node target modes.
 * Records published by 1.0 keep their Cloudflare ids, rebuilt into the full records 1.0 created, so nothing is republished.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_subdomains_domains', function (Blueprint $table): void {
            $table->string('provider', 16)->default('cloudflare')->after('name');
            $table->string('zone_id', 191)->change();
            $table->string('zone_name', 191)->nullable()->after('zone_id');
            $table->text('credentials')->nullable()->after('zone_name');
            $table->json('eggs')->nullable()->after('credentials');
        });

        Schema::table('ext_subdomains_records', function (Blueprint $table): void {
            $table->unsignedInteger('allocation_id')->nullable()->after('server_uuid');
            $table->string('fallback_from', 64)->nullable()->after('allocation_id');
            $table->string('note', 255)->nullable()->after('port');
            $table->json('dns_records')->nullable()->after('note');
        });

        Schema::table('ext_subdomains_nodes', function (Blueprint $table): void {
            $table->string('mode', 16)->default('ip')->after('node_id');
            $table->string('hostname', 191)->nullable()->after('ipv6');
        });

        Schema::create('ext_subdomains_templates', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 32)->unique();
            $table->unsignedInteger('egg_id')->nullable()->unique();
            $table->json('template');
            $table->timestamps();

            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
        });

        $this->rebuildCloudflareRecords();
        $this->copyCloudflareToken();

        Schema::table('ext_subdomains_records', function (Blueprint $table): void {
            $table->dropColumn('cloudflare_ids');
        });
    }

    public function down(): void
    {
        Schema::table('ext_subdomains_records', function (Blueprint $table): void {
            $table->json('cloudflare_ids')->nullable()->after('port');
        });

        DB::table('ext_subdomains_records')->whereNotNull('dns_records')->orderBy('id')->each(function (object $row): void {
            $ids = [];

            foreach (json_decode((string) $row->dns_records, true) ?: [] as $record) {
                if (! empty($record['id'])) {
                    $ids[mb_strtolower((string) $record['type'])] = $record['id'];
                }
            }

            DB::table('ext_subdomains_records')->where('id', $row->id)->update(['cloudflare_ids' => $ids === [] ? null : json_encode($ids)]);
        });

        Schema::dropIfExists('ext_subdomains_templates');

        Schema::table('ext_subdomains_nodes', function (Blueprint $table): void {
            $table->dropColumn(['mode', 'hostname']);
        });

        Schema::table('ext_subdomains_records', function (Blueprint $table): void {
            $table->dropColumn(['allocation_id', 'fallback_from', 'note', 'dns_records']);
        });

        Schema::table('ext_subdomains_domains', function (Blueprint $table): void {
            $table->dropColumn(['provider', 'zone_name', 'credentials', 'eggs']);
        });
    }

    /** 1.0 stored only Cloudflare ids; the records behind them followed fixed rules, so they can be written out in full. */
    private function rebuildCloudflareRecords(): void
    {
        DB::table('ext_subdomains_records as r')
            ->join('ext_subdomains_domains as d', 'd.id', '=', 'r.domain_id')
            ->leftJoin('servers as s', 's.id', '=', 'r.server_id')
            ->leftJoin('ext_subdomains_nodes as n', 'n.node_id', '=', 's.node_id')
            ->whereNotNull('r.cloudflare_ids')
            ->select(['r.id', 'r.name', 'r.target', 'r.port', 'r.cloudflare_ids', 'd.name as domain', 'n.ipv6'])
            ->orderBy('r.id')
            ->each(function (object $row): void {
                $fqdn = "{$row->name}.{$row->domain}";
                $records = [];

                foreach (json_decode((string) $row->cloudflare_ids, true) ?: [] as $kind => $id) {
                    $record = match ($kind) {
                        'a' => ['type' => 'A', 'name' => $fqdn, 'content' => (string) $row->target, 'priority' => 0, 'weight' => 0, 'port' => 0],
                        'aaaa' => ['type' => 'AAAA', 'name' => $fqdn, 'content' => (string) ($row->ipv6 ?? $row->target), 'priority' => 0, 'weight' => 0, 'port' => 0],
                        'srv' => ['type' => 'SRV', 'name' => "_minecraft._tcp.{$fqdn}", 'content' => $fqdn, 'priority' => 0, 'weight' => 5, 'port' => (int) $row->port],
                        default => null,
                    };

                    if ($record !== null) {
                        $records[] = $record + ['ttl' => 1, 'proxied' => false, 'comment' => '', 'id' => (string) $id];
                    }
                }

                DB::table('ext_subdomains_records')->where('id', $row->id)->update(['dns_records' => json_encode($records)]);
            });
    }

    /** The global token stays as a fallback; existing domains get their own copy, so they keep working on their own. */
    private function copyCloudflareToken(): void
    {
        $token = (string) (app(ExtensionRepository::class)->settings('subdomains')->get('cloudflare_token') ?? '');

        if ($token !== '') {
            DB::table('ext_subdomains_domains')
                ->where('provider', 'cloudflare')
                ->whereNull('credentials')
                ->update(['credentials' => Crypt::encryptString(json_encode(['token' => $token]))]);
        }
    }
};
