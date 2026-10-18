<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aspectul cardului din wizard (SDD §7, pasul 3): culoarea și iconul ștampilei (ADR 008).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('rollover_extra');
            $table->string('stamp_icon', 20)->default('star')->after('color');
        });

        DB::statement("ALTER TABLE programs ADD CONSTRAINT programs_color_check CHECK (color IS NULL OR REGEXP_LIKE(color, '^#[0-9a-fA-F]{6}$'))");
        DB::statement("ALTER TABLE programs ADD CONSTRAINT programs_stamp_icon_check CHECK (stamp_icon IN ('coffee', 'utensils', 'cake', 'car', 'scissors', 'star', 'heart', 'gift'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE programs DROP CHECK programs_stamp_icon_check');
        DB::statement('ALTER TABLE programs DROP CHECK programs_color_check');

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['color', 'stamp_icon']);
        });
    }
};
