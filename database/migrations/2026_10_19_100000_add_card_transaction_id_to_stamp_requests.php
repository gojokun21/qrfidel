<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cererea aprobată ține minte ștampila pe care a produs-o (ADR 012): replay la dublu-click și audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stamp_requests', function (Blueprint $table) {
            $table->foreignId('card_transaction_id')->nullable()->after('handled_at')->constrained()->restrictOnDelete();
        });

        DB::statement("ALTER TABLE stamp_requests ADD CONSTRAINT stamp_requests_approved_transaction_check CHECK (status <> 'approved' OR card_transaction_id IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stamp_requests DROP CONSTRAINT IF EXISTS stamp_requests_approved_transaction_check');

        Schema::table('stamp_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('card_transaction_id');
        });
    }
};
