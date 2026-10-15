<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.2: how names are chosen per domain. Existing domains keep names typed by users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_subdomains_domains', function (Blueprint $table): void {
            $table->string('naming_mode', 16)->default('custom')->after('eggs');
            $table->string('naming_pattern', 100)->nullable()->after('naming_mode');
            $table->unsignedTinyInteger('naming_length')->default(8)->after('naming_pattern');
        });
    }

    public function down(): void
    {
        Schema::table('ext_subdomains_domains', function (Blueprint $table): void {
            $table->dropColumn(['naming_mode', 'naming_pattern', 'naming_length']);
        });
    }
};
