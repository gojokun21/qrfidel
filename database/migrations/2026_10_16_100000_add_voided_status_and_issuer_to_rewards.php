<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Anularea unei ștampile revocă recompensa pe care a emis-o (ADR 004): status `voided`
 * și legătura recompensei cu tranzacția `stamp` care a emis-o.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->foreignId('issued_by_transaction_id')->nullable()->after('card_id')
                ->constrained('card_transactions')->restrictOnDelete();
            $table->index('issued_by_transaction_id');
        });

        DB::statement('ALTER TABLE rewards DROP CHECK rewards_status_check');
        DB::statement("ALTER TABLE rewards ADD CONSTRAINT rewards_status_check CHECK (status IN ('available', 'redeemed', 'expired', 'voided'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE rewards SET status = 'expired' WHERE status = 'voided'");
        DB::statement('ALTER TABLE rewards DROP CHECK rewards_status_check');
        DB::statement("ALTER TABLE rewards ADD CONSTRAINT rewards_status_check CHECK (status IN ('available', 'redeemed', 'expired'))");

        Schema::table('rewards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_by_transaction_id');
        });
    }
};
