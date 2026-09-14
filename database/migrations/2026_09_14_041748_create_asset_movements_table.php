<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The asset movement ledger. Append-only: a recorded row is never edited or
     * deleted; a correction is a new movement.
     */
    public function up(): void
    {
        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('movement_type', 30)->comment('initial|assignment|return|transfer|maintenance|repair|disposal|audit_adjustment');
            $table->dateTime('moved_at');

            $table->string('from_placement_type', 20)->nullable();
            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('from_employee_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->string('to_placement_type', 20);
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_employee_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->string('condition_before', 30)->nullable();
            $table->string('condition_after', 30)->nullable();
            $table->string('status_before', 30)->nullable();
            $table->string('status_after', 30)->nullable();

            $table->nullableMorphs('reference');
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'moved_at']);
            $table->index(['to_employee_id', 'moved_at']);
            $table->index(['to_location_id', 'moved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_movements');
    }
};
