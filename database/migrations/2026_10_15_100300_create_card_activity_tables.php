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
            $table->jsonb('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Unic per afacere: o cheie generată de clientul unei afaceri nu poate bloca acțiunile alteia.
            $table->unique(['business_id', 'idempotency_key']);
            $table->index(['card_id', 'created_at']);
            $table->index(['business_id', 'created_at']);
        });

        DB::statement("ALTER TABLE card_transactions ADD CONSTRAINT card_transactions_type_check CHECK (type IN ('stamp', 'redeem', 'void', 'expire', 'adjust'))");
        DB::statement("ALTER TABLE card_transactions ADD CONSTRAINT card_transactions_source_check CHECK (source IS NULL OR source IN ('scan', 'request', 'manual'))");

        // Jurnal append-only: singura modificare permisă e marcarea o singură dată ca anulată (voided_by_id).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION card_transactions_append_only() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE'
                    AND OLD.voided_by_id IS NULL
                    AND NEW.voided_by_id IS NOT NULL
                    AND NEW.voided_by_id <> OLD.id
                    AND (to_jsonb(NEW) - 'voided_by_id') = (to_jsonb(OLD) - 'voided_by_id')
                    AND EXISTS (
                        SELECT 1 FROM card_transactions v
                        WHERE v.id = NEW.voided_by_id AND v.card_id = OLD.card_id AND v.type = 'void'
                    )
                THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'card_transactions is append-only (% refused on id %)', TG_OP, OLD.id;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER card_transactions_append_only
                BEFORE UPDATE OR DELETE ON card_transactions
                FOR EACH ROW EXECUTE FUNCTION card_transactions_append_only();
            SQL);

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('card_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('available');
            $table->timestamp('issued_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

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
            $table->timestamp('handled_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['business_id', 'status', 'created_at']);
            $table->index(['location_id', 'status']);
            $table->index('card_id');
        });

        DB::statement("ALTER TABLE stamp_requests ADD CONSTRAINT stamp_requests_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'expired'))");
        DB::statement('ALTER TABLE stamp_requests ADD CONSTRAINT stamp_requests_quantity_check CHECK (quantity >= 1)');
        // O singură cerere pending per card (SDD §5.3).
        DB::statement("CREATE UNIQUE INDEX stamp_requests_one_pending_per_card ON stamp_requests (card_id) WHERE status = 'pending'");

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->restrictOnDelete();
            $table->nullableMorphs('actor');
            $table->string('action', 100);
            $table->nullableMorphs('subject');
            $table->jsonb('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('stamp_requests');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('card_transactions');
        DB::unprepared('DROP FUNCTION IF EXISTS card_transactions_append_only()');
    }
};
