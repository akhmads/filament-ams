<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One asset in an audit: where the register said it was, where it was found,
     * and which corrections the reviewer chose.
     */
    public function up(): void
    {
        Schema::create('asset_audit_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->boolean('is_expected')->default(true)->comment('False for an asset scanned that was not on the audit list');
            $table->foreignId('expected_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('expected_condition', 20)->nullable();
            $table->string('result', 20)->default('pending')->comment('pending|found|misplaced|missing');
            $table->dateTime('scanned_at')->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('scanned_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('observed_condition', 20)->nullable();
            $table->boolean('apply_relocation')->default(false);
            $table->boolean('apply_condition')->default(false);
            $table->boolean('mark_lost')->default(false);
            $table->dateTime('applied_at')->nullable();
            $table->foreignId('adjustment_movement_id')->nullable()->constrained('asset_movements')->nullOnDelete();
            $table->timestamps();

            $table->unique(['asset_audit_id', 'asset_id']);
            $table->index(['asset_audit_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_audit_lines');
    }
};
