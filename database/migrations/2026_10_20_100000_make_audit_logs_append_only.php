<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Jurnalul de audit e append-only (ADR 013): nicio modificare, nicio ștergere, nici prin query builder.
 * TRUNCATE (folosit de testele de concurență) nu declanșează triggerele pe rând.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['UPDATE', 'DELETE'] as $operation) {
            $name = 'audit_logs_append_only_'.strtolower($operation);

            DB::unprepared(<<<SQL
                CREATE TRIGGER {$name} BEFORE {$operation} ON audit_logs
                FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only ({$operation} refused)'
                SQL);
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_append_only_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_append_only_delete');
    }
};
