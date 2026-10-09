<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_subdomains_domains', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name', 191)->unique();
            $table->string('zone_id', 64);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('ext_subdomains_records', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('domain_id');
            $table->unsignedInteger('server_id')->nullable();
            $table->char('server_uuid', 36)->index();
            $table->string('name', 63);
            $table->string('target', 191)->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->json('cloudflare_ids')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->unique(['domain_id', 'name']);
            $table->foreign('domain_id')->references('id')->on('ext_subdomains_domains')->cascadeOnDelete();
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
        });

        Schema::create('ext_subdomains_limits', function (Blueprint $table): void {
            $table->unsignedInteger('server_id')->primary();
            $table->unsignedSmallInteger('limit');
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });

        Schema::create('ext_subdomains_nodes', function (Blueprint $table): void {
            $table->unsignedInteger('node_id')->primary();
            $table->string('ipv4', 45)->nullable();
            $table->string('ipv6', 45)->nullable();
            $table->timestamps();

            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_subdomains_nodes');
        Schema::dropIfExists('ext_subdomains_limits');
        Schema::dropIfExists('ext_subdomains_records');
        Schema::dropIfExists('ext_subdomains_domains');
    }
};
