<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('card_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->integer('quantity');
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source', 20)->nullable();
            $table->string('idempotency_key')->nullable();
            $table->foreignId('voided_by_id')->nullable()->unique()->constrained('card_transactions')->restrictOnDelete();
            $table->json('meta')->nullable();
            $table->dateTime('created_at')->useCurrent();

            // Unic per afacere: o cheie generată de clientul unei afaceri nu poate bloca acțiunile alteia.
            $table->unique(['business_id', 'idempotency_key']);
            $table->index(['card_id', 'created_at']);
            $table->index(['business_id', 'created_at']);
        });

        DB::statement("ALTER TABLE card_transactions ADD CONSTRAINT card_transactions_type_check CHECK (type IN ('stamp', 'redeem', 'void', 'expire', 'adjust'))");
        DB::statement("ALTER TABLE card_transactions ADD CONSTRAINT card_transactions_source_check CHECK (source IS NULL OR source IN ('scan', 'request', 'manual'))");

        // Jurnal append-only: singura modificare permisă e marcarea o singură dată ca anulată (voided_by_id),
        // de către o tranzacție void a aceluiași card; toate celelalte coloane rămân neschimbate.
        // Lista de coloane e explicită (MySQL n-are comparația rândului întreg): o coloană nouă se adaugă
        // aici, iar SchemaTest pică dacă e uitată.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER card_transactions_append_only_update BEFORE UPDATE ON card_transactions
            FOR EACH ROW
            BEGIN
                IF NOT COALESCE(
                    OLD.voided_by_id IS NULL
                    AND NEW.voided_by_id IS NOT NULL
                    AND NEW.voided_by_id <> OLD.id
                    AND NEW.id <=> OLD.id
                    AND NEW.business_id <=> OLD.business_id
                    AND NEW.card_id <=> OLD.card_id
                    AND NEW.type <=> OLD.type
                    AND NEW.quantity <=> OLD.quantity
                    AND NEW.staff_user_id <=> OLD.staff_user_id
                    AND NEW.location_id <=> OLD.location_id
                    AND NEW.source <=> OLD.source
                    AND NEW.idempotency_key <=> OLD.idempotency_key
                    AND NEW.meta <=> OLD.meta
                    AND NEW.created_at <=> OLD.created_at
                    AND EXISTS (
                        SELECT 1 FROM card_transactions v
                        WHERE v.id = NEW.voided_by_id AND v.card_id = OLD.card_id AND v.type = 'void'
                    ),
                    FALSE
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'card_transactions is append-only (UPDATE refused)';
                END IF;
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER card_transactions_append_only_delete BEFORE DELETE ON card_transactions
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'card_transactions is append-only (DELETE refused)'
            SQL);

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('card_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('available');
            $table->dateTime('issued_at');
            $table->dateTime('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('expires_at')->nullable();
            $table->datetimes();

            $table->index(['card_id', 'status', 'issued_at']);
            $table->index(['business_id', 'status']);
        });

        DB::statement("ALTER TABLE rewards ADD CONSTRAINT rewards_status_check CHECK (status IN ('available', 'redeemed', 'expired'))");

        Schema::create('stamp_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('card_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->foreignId('handled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('handled_at')->nullable();
            $table->dateTime('expires_at');
            $table->datetimes();

            $table->index(['business_id', 'status', 'created_at']);
            $table->index(['location_id', 'status']);
            $table->index('card_id');
        });

        DB::statement("ALTER TABLE stamp_requests ADD CONSTRAINT stamp_requests_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'expired'))");
        DB::statement('ALTER TABLE stamp_requests ADD CONSTRAINT stamp_requests_quantity_check CHECK (quantity >= 1)');
        // O singură cerere pending per card (SDD §5.3). MySQL n-are indexuri parțiale: indexul funcțional e NULL
        // pentru celelalte statusuri, iar NULL-urile nu se ciocnesc într-un index unic.
        DB::statement("CREATE UNIQUE INDEX stamp_requests_one_pending_per_card ON stamp_requests ((CASE WHEN status = 'pending' THEN card_id END))");

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->restrictOnDelete();
            $table->nullableMorphs('actor');
            $table->string('action', 100);
            $table->nullableMorphs('subject');
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('stamp_requests');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('card_transactions');
    }
};
