<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** -1 stores an unlimited limit. */
    public function up(): void
    {
        Schema::table('ext_subdomains_limits', function (Blueprint $table): void {
            $table->smallInteger('limit')->change();
        });
    }

    public function down(): void
    {
        Schema::table('ext_subdomains_limits', function (Blueprint $table): void {
            $table->unsignedSmallInteger('limit')->change();
        });
    }
};
