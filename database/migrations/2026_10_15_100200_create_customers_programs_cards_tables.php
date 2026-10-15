<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Unic indiferent de majuscule (modelul salvează oricum emailul cu litere mici).
        DB::statement('CREATE UNIQUE INDEX customers_email_unique ON customers (LOWER(email))');

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('stamps');
            $table->unsignedSmallInteger('stamps_required');
            $table->string('reward_title');
            $table->text('reward_description')->nullable();
            $table->unsignedSmallInteger('max_stamps_per_visit')->default(1);
            $table->unsignedInteger('cooldown_minutes')->default(30);
            $table->unsignedSmallInteger('daily_limit')->nullable()->default(1);
            $table->unsignedSmallInteger('stamps_expire_days')->nullable();
            $table->unsignedSmallInteger('reward_expire_days')->nullable();
            $table->boolean('rollover_extra')->default(false);
            $table->string('status', 20)->default('draft');
            $table->text('terms')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });

        DB::statement("ALTER TABLE programs ADD CONSTRAINT programs_type_check CHECK (type IN ('stamps'))");
        DB::statement("ALTER TABLE programs ADD CONSTRAINT programs_status_check CHECK (status IN ('draft', 'active', 'paused', 'archived'))");
        DB::statement('ALTER TABLE programs ADD CONSTRAINT programs_rules_check CHECK (
            stamps_required >= 1
            AND max_stamps_per_visit >= 1
            AND cooldown_minutes >= 0
            AND (daily_limit IS NULL OR daily_limit >= 1)
            AND (stamps_expire_days IS NULL OR stamps_expire_days >= 1)
            AND (reward_expire_days IS NULL OR reward_expire_days >= 1)
        )');

        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->text('secret');
            $table->integer('stamps_balance')->default(0);
            $table->integer('rewards_available')->default(0);
            $table->timestamp('last_stamp_at')->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'customer_id']);
            $table->index('customer_id');
            $table->index('business_id');
        });

        DB::statement('ALTER TABLE cards ADD CONSTRAINT cards_balances_check CHECK (stamps_balance >= 0 AND rewards_available >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('customers');
    }
};
