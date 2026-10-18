<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('email');
            $table->string('role', 20);
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            // Doar hash-ul (sha256, hex); tokenul în clar apare numai în email.
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('revoked_at')->nullable();
            $table->datetimes();

            $table->index('business_id');
        });

        DB::statement("ALTER TABLE invitations ADD CONSTRAINT invitations_role_check CHECK (role IN ('owner', 'manager', 'staff'))");

        // Ownerul nu e legat de o locație. Trigger, nu CHECK: MySQL nu acceptă un CHECK pe o coloană cu
        // ON DELETE SET NULL (location_id); setarea pe NULL respectă oricum regula.
        foreach (['INSERT', 'UPDATE'] as $operation) {
            $name = 'invitations_owner_without_location_'.strtolower($operation);

            DB::unprepared(<<<SQL
                CREATE TRIGGER {$name} BEFORE {$operation} ON invitations
                FOR EACH ROW
                BEGIN
                    IF NEW.role = 'owner' AND NEW.location_id IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invitations_owner_without_location';
                    END IF;
                END
                SQL);
        }

        // O singură invitație activă per afacere și email (re-invitarea o revocă pe cea veche). Index funcțional în
        // locul unuia parțial: pentru invitațiile acceptate sau revocate, expresiile sunt NULL și nu se ciocnesc.
        DB::statement('CREATE UNIQUE INDEX invitations_one_active_per_email ON invitations (
            (CASE WHEN accepted_at IS NULL AND revoked_at IS NULL THEN business_id END),
            (CASE WHEN accepted_at IS NULL AND revoked_at IS NULL THEN lower(email) END)
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
