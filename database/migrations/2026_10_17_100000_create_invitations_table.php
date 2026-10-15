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
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('business_id');
        });

        DB::statement("ALTER TABLE invitations ADD CONSTRAINT invitations_role_check CHECK (role IN ('owner', 'manager', 'staff'))");
        DB::statement('ALTER TABLE invitations ADD CONSTRAINT invitations_owner_without_location CHECK (role <> \'owner\' OR location_id IS NULL)');

        // O singură invitație activă per afacere și email (re-invitarea o revocă pe cea veche).
        DB::statement('CREATE UNIQUE INDEX invitations_one_active_per_email ON invitations (business_id, lower(email)) WHERE accepted_at IS NULL AND revoked_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
