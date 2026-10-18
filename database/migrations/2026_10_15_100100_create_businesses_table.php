<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 20);
            $table->string('logo')->nullable();
            $table->string('brand_color', 7)->nullable();
            $table->string('timezone', 64)->default('Europe/Bucharest');
            $table->string('status', 20)->default('active');
            $table->datetimes();
        });

        DB::statement("ALTER TABLE businesses ADD CONSTRAINT businesses_type_check CHECK (type IN ('cafe', 'restaurant', 'carwash', 'other'))");
        DB::statement("ALTER TABLE businesses ADD CONSTRAINT businesses_status_check CHECK (status IN ('active', 'suspended'))");

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('counter_token', 40)->unique();
            $table->datetimes();
        });

        Schema::create('business_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->datetimes();

            $table->unique(['business_id', 'user_id']);
            $table->index('user_id');
        });

        DB::statement("ALTER TABLE business_user ADD CONSTRAINT business_user_role_check CHECK (role IN ('owner', 'manager', 'staff'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('business_user');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('businesses');
    }
};
