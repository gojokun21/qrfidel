<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Store-ul `loyalty_database` (ADR 022): jti-uri, coduri scurte, tichete de scanare și nonce-uri pe hostingul fără Redis.
 * Tabel separat de `cache`, ca `cache:clear` să nu reactiveze coduri sau linkuri folosite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });

        Schema::create('loyalty_cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_cache');
        Schema::dropIfExists('loyalty_cache_locks');
    }
};
